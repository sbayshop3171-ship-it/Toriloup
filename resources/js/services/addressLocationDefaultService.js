import locationAutocompleteService from "./locationAutocompleteService";

function normalize(value) {
    return (value || "").toString().toLowerCase().replace(/[^a-z0-9]/g, "");
}

function findCountry(countries, countryCode = null, countryName = null) {
    const normalizedCode = (countryCode || "").toString().toUpperCase();
    const normalizedName = normalize(countryName);

    return (countries || []).find((country) => {
        if (normalizedCode && country.code?.toUpperCase() === normalizedCode) {
            return true;
        }

        return normalizedName && normalize(country.name) === normalizedName;
    }) || null;
}

function findCountryCode(countryCodes, countryCode = null, callingCode = null) {
    const normalizedCode = (countryCode || "").toString().toUpperCase();

    return (countryCodes || []).find((item) => {
        return normalizedCode && item.country_code?.toUpperCase() === normalizedCode;
    }) || (countryCodes || []).find((item) => {
        return callingCode && item.calling_code === callingCode;
    }) || null;
}

function isBlank(value) {
    return value === null || value === undefined || String(value).trim() === "";
}

function setIfBlank(target, key, value) {
    if (isBlank(target?.[key]) && !isBlank(value)) {
        target[key] = value;
    }
}

function composeAddressLine(location = {}) {
    const parts = [
        location.streetAddress || location.address,
        location.city,
        location.state,
        location.countryName || location.country?.name,
        location.zipCode,
    ].filter((part, index, items) => {
        return !isBlank(part) && items.findIndex((item) => normalize(item) === normalize(part)) === index;
    });

    return parts.join(", ");
}

const addressLocationDefaultService = {
    async resolve(countries, countryCodes) {
        try {
            const detected = await locationAutocompleteService.detectCountryByIp();
            if (!detected?.country_code && !detected?.country_name) {
                return null;
            }

            const country = findCountry(countries, detected.country_code, detected.country_name);
            const countryCode = findCountryCode(countryCodes, detected.country_code, detected.calling_code);

            return {
                country,
                countryName: country?.name || detected.country_name || null,
                countryCode: detected.country_code || country?.code || null,
                callingCode: countryCode?.calling_code || detected.calling_code || null,
                flagEmoji: countryCode?.flag_emoji || detected.flag_emoji || null,
                state: detected.state || null,
                city: detected.city || null,
                zipCode: detected.zip_code || null,
                address: composeAddressLine({
                    city: detected.city || null,
                    state: detected.state || null,
                    countryName: country?.name || detected.country_name || null,
                    zipCode: detected.zip_code || null,
                }),
                latitude: detected.latitude || null,
                longitude: detected.longitude || null,
                source: detected.source || "ip",
            };
        } catch (error) {
            return null;
        }
    },

    applyProfileDefaults(form, profile = {}) {
        if (!form || !profile) {
            return;
        }

        setIfBlank(form, "full_name", profile.name);
        setIfBlank(form, "email", profile.email);
        setIfBlank(form, "phone", profile.phone);
        setIfBlank(form, "country_code", profile.country_code);
    },

    applyLocationDefaults(form, location = {}, options = {}) {
        if (!form || !location) {
            return;
        }

        const forceAddress = options.forceAddress === true;
        const applyPostalCode = options.applyPostalCode === true || forceAddress;
        const allowApproximateAddress = options.allowApproximateAddress === true || forceAddress;

        if (applyPostalCode) {
            setIfBlank(form, "zip_code", location.zipCode);
        }

        if (allowApproximateAddress && (forceAddress || isBlank(form.address)) && !isBlank(location.address)) {
            form.address = location.address;
        }

        if (!isBlank(location.latitude)) {
            form.latitude = location.latitude;
        }

        if (!isBlank(location.longitude)) {
            form.longitude = location.longitude;
        }
    },

    composeAddressLine,
    findCountry,
    findCountryCode,
};

export default addressLocationDefaultService;
