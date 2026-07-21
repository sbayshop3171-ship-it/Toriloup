import axios from "axios";

const LOCATION_TYPES = Object.freeze({
    ADDRESS: "address",
    POI: "poi",
    COUNTRY: "country",
    REGION: "region",
    PLACE: "place",
    LOCALITY: "locality",
    NEIGHBORHOOD: "neighborhood",
    DISTRICT: "district",
    POSTCODE: "postcode",
});

function findInContext(feature, type) {
    if (!Array.isArray(feature.context)) {
        return null;
    }

    return feature.context.find((contextItem) => contextItem.id && contextItem.id.startsWith(type + "."));
}

function featureTypeValue(feature, type) {
    if (Array.isArray(feature.place_type) && feature.place_type.includes(type)) {
        return feature;
    }

    return findInContext(feature, type);
}

function resolveCity(feature) {
    const place = featureTypeValue(feature, LOCATION_TYPES.PLACE);
    if (place?.text) {
        return place.text;
    }

    const locality = featureTypeValue(feature, LOCATION_TYPES.LOCALITY);
    if (locality?.text) {
        return locality.text;
    }

    const district = featureTypeValue(feature, LOCATION_TYPES.DISTRICT);
    return district?.text ?? null;
}

function resolveState(feature) {
    const state = featureTypeValue(feature, LOCATION_TYPES.REGION);
    return state?.text ?? null;
}

function resolveCountry(feature) {
    const country = featureTypeValue(feature, LOCATION_TYPES.COUNTRY);
    return country?.text ?? null;
}

function resolveCountryCode(feature) {
    const country = featureTypeValue(feature, LOCATION_TYPES.COUNTRY);
    if (!country?.short_code) {
        return null;
    }

    return country.short_code.toUpperCase();
}

function resolvePostalCode(feature) {
    const postcode = featureTypeValue(feature, LOCATION_TYPES.POSTCODE);
    return postcode?.text ?? null;
}

function resolveStreetAddress(feature) {
    if (feature.address && feature.text) {
        return `${feature.address} ${feature.text}`.trim();
    }

    return feature.place_name || feature.text || "";
}

function featurePriority(feature) {
    const placeTypes = Array.isArray(feature.place_type) ? feature.place_type : [];
    const weights = {
        [LOCATION_TYPES.ADDRESS]: 100,
        [LOCATION_TYPES.POI]: 90,
        [LOCATION_TYPES.NEIGHBORHOOD]: 70,
        [LOCATION_TYPES.LOCALITY]: 60,
        [LOCATION_TYPES.PLACE]: 50,
        [LOCATION_TYPES.POSTCODE]: 40,
        [LOCATION_TYPES.DISTRICT]: 30,
        [LOCATION_TYPES.REGION]: 20,
        [LOCATION_TYPES.COUNTRY]: 10,
    };

    const typeScore = placeTypes.reduce((score, type) => Math.max(score, weights[type] || 0), 0);

    return typeScore
        + (feature.address ? 8 : 0)
        + (resolvePostalCode(feature) ? 3 : 0)
        + (resolveCity(feature) ? 2 : 0)
        + (resolveState(feature) ? 1 : 0);
}

function pickBestReverseFeature(features) {
    return [...features].sort((a, b) => featurePriority(b) - featurePriority(a))[0] || null;
}

function mapFeature(feature) {
    return {
        id: feature.id,
        label: feature.place_name,
        street_address: resolveStreetAddress(feature),
        country: resolveCountry(feature),
        country_code: resolveCountryCode(feature),
        state: resolveState(feature),
        city: resolveCity(feature),
        zip_code: resolvePostalCode(feature),
        latitude: Array.isArray(feature.center) ? feature.center[1] : null,
        longitude: Array.isArray(feature.center) ? feature.center[0] : null,
    };
}

const locationAutocompleteService = {
    async detectCountryByIp() {
        const response = await axios.get("frontend/location/detect");
        return response.data?.data || null;
    },

    async searchAddressSuggestions(query, accessToken, countryCode = null) {
        if (!accessToken || !query || query.trim().length < 3) {
            return [];
        }

        const encodedQuery = encodeURIComponent(query.trim());
        const params = new URLSearchParams({
            access_token: accessToken,
            autocomplete: "true",
            limit: "6",
            language: "en",
            types: "address,place,postcode,locality,region",
        });

        if (countryCode) {
            params.append("country", countryCode.toLowerCase());
        }

        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodedQuery}.json?${params.toString()}`;
        const response = await axios.get(url);

        if (!Array.isArray(response.data?.features)) {
            return [];
        }

        return response.data.features.map((feature) => mapFeature(feature));
    },

    detectCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error("Geolocation is not supported by this browser."));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                    });
                },
                (error) => {
                    reject(error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                }
            );
        });
    },

    async reverseGeocodeByCoordinates(latitude, longitude, accessToken, countryCode = null) {
        if (!accessToken || latitude === null || latitude === undefined || longitude === null || longitude === undefined) {
            return null;
        }

        const params = new URLSearchParams({
            access_token: accessToken,
            language: "en",
            types: "address,poi,neighborhood,place,postcode,locality,district,region,country",
            limit: "5",
        });

        if (countryCode) {
            params.append("country", countryCode.toLowerCase());
        }

        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${longitude},${latitude}.json?${params.toString()}`;
        const response = await axios.get(url);
        const features = Array.isArray(response?.data?.features) ? response.data.features : [];
        const feature = pickBestReverseFeature(features);

        if (!feature) {
            return null;
        }

        return mapFeature(feature);
    },

    async detectAddressByCountry(accessToken, countryCode = null) {
        const coordinates = await this.detectCurrentPosition();
        const location = await this.reverseGeocodeByCoordinates(
            coordinates.latitude,
            coordinates.longitude,
            accessToken,
            countryCode
        );

        if (!location) {
            return null;
        }

        return {
            ...location,
            latitude: coordinates.latitude,
            longitude: coordinates.longitude,
        };
    },
};

export default locationAutocompleteService;
