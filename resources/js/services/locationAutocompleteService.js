import axios from "axios";

const LOCATION_TYPES = Object.freeze({
    ADDRESS: "address",
    COUNTRY: "country",
    REGION: "region",
    PLACE: "place",
    LOCALITY: "locality",
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

    if (feature.text) {
        return feature.text;
    }

    return feature.place_name ?? "";
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
};

export default locationAutocompleteService;
