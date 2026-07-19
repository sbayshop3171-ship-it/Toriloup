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
                source: detected.source || "ip",
            };
        } catch (error) {
            return null;
        }
    },

    findCountry,
    findCountryCode,
};

export default addressLocationDefaultService;
