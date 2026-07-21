<template>
    <LoadingComponent :props="loading" />

    <button data-modal="#address" @click="showTarget()" type="button"
        class="w-full rounded-2xl py-10 flex items-center justify-center gap-2.5 text-primary bg-[#FFF4F1]">
        <i class="lab-fill-circle-plus text-lg"></i>
        <span class="text-lg font-semibold capitalize">{{ addButton.title }}</span>
    </button>
    <div id="address"
        class="fixed inset-0 z-50 p-3 w-screen h-dvh overflow-y-auto bg-black/50 transition-all duration-300 opacity-0 invisible">
        <div class="w-full rounded-xl mx-auto bg-white transition-all duration-300 max-w-3xl">
            <div class="flex items-center justify-between gap-2 py-4 px-4 border-b border-slate-100">
                <h3 class="text-lg font-bold capitalize">{{ $t('label.address') }}</h3><button @click="reset()"
                    type="button" class="lab-line-circle-cross text-lg text-[#E93C3C]"></button>
            </div>
            <form class="w-full p-5" @submit.prevent="save">
                <div class="form-row">
                    <div class="form-col-12 sm:form-col-6"><label for="full_name"
                            class="text-sm font-medium capitalize mb-1 field-title required">{{ $t('label.full_name')
                            }}</label><input type="text" v-model="props.form.full_name"
                            :class="errors.full_name ? 'invalid' : ''"
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500">
                        <small class="db-field-alert" v-if="errors.full_name">
                            {{ errors.full_name[0] }}
                        </small>
                    </div>
                    <div class="form-col-12 sm:form-col-6">
                        <label for="email" class="text-sm font-medium capitalize mb-1 field-title">{{
                            $t("label.email")
                        }}</label>
                        <input type="email" v-model="props.form.email" :class="errors.email ? 'invalid' : ''"
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500">
                        <small class="db-field-alert" v-if="errors.email">
                            {{ errors.email[0] }}
                        </small>
                    </div>

                    <div class="form-col-12 sm:form-col-6">
                        <label for="phone" class="text-sm font-medium capitalize mb-1 field-title required">{{
                            $t("label.phone")
                        }}</label>
                        <div :class="errors.phone ? 'invalid' : ''" class="field-control flex items-center">
                            <div class="w-fit flex-shrink-0 dropdown-group">
                                <button type="button" class="flex items-center gap-1 dropdown-btn">
                                    {{ props.flag }}
                                    <span class="whitespace-nowrap flex-shrink-0 text-xs">{{ props.form.country_code
                                        }}</span>
                                    <i class="fa-solid fa-caret-down text-xs"></i>
                                </button>
                                <ul
                                    class="p-1.5 w-24 rounded-lg shadow-xl absolute top-8 -left-4 z-10 border border-gray-200 bg-white scale-y-0 origin-top dropdown-list !h-52 !overflow-x-hidden !overflow-y-auto thin-scrolling">
                                    <li v-for="countryCode in countryCodes" @click="changeCountry(countryCode)"
                                        class="flex items-center gap-2 p-1.5 rounded-md cursor-pointer hover:bg-gray-100">
                                        {{ countryCode.flag_emoji }}
                                        <span class="whitespace-nowrap text-xs">{{ countryCode.calling_code }}</span>
                                    </li>
                                </ul>

                            </div>
                            <input v-model="props.form.phone" v-on:keypress="phoneNumber($event)" v-bind:class="errors.phone
                                ? 'invalid' : ''" type="text" id="phone" class="pl-2 text-sm w-full h-full" />
                        </div>

                        <small class="db-field-alert" v-if="errors.phone">
                            {{ errors.phone[0] }}
                        </small>
                    </div>

                    <div class="form-col-12 sm:form-col-6"><label
                            class="text-sm font-medium capitalize mb-1 field-title required" for="country">{{
                                $t('label.country') }}</label>
                        <vue-select
                            class="frontend-select w-full h-12 px-4 rounded-lg text-base capitalize border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500 appearance-none"
                            id="country" v-bind:class="errors.country ? 'invalid' : ''" v-model="props.form.country"
                            @update:modelValue="handleCountryChange($event)" :options="countries" label-by="name" value-by="name"
                            :closeOnSelect="true" :searchable="true" :clearOnClose="true" placeholder="--"
                            search-placeholder="--" />
                        <small class="db-field-alert" v-if="errors.country">
                            {{ errors.country[0] }}
                        </small>
                    </div>
                    <div class="form-col-12 sm:form-col-6" v-if="props.form.country"><label
                            class="text-sm font-medium capitalize mb-1 field-title" for="state">{{
                                $t('label.state') }}</label>
                        <vue-select
                            class="frontend-select w-full h-12 px-4 rounded-lg text-base capitalize border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500 appearance-none"
                            id="state" v-bind:class="errors.state ? 'invalid' : ''" v-model="props.form.state"
                            @update:modelValue="callCities($event)" :options="props.states" label-by="name"
                            value-by="name" :closeOnSelect="true" :searchable="true" :clearOnClose="true"
                            :disabled="props.states.length === 0"
                            placeholder="--" search-placeholder="--" />
                        <small class="db-field-alert" v-if="errors.state">
                            {{ errors.state[0] }}
                        </small>
                        <small class="db-field-alert !text-slate-500" v-if="props.states.length === 0">
                            {{ $t('message.no_states_available') }}
                        </small>
                    </div>
                    <div class="form-col-12 sm:form-col-6" v-else>
                        <label class="text-sm font-medium capitalize mb-1 field-title" for="state-placeholder">
                            {{ $t('label.state') }}
                        </label>
                        <input id="state-placeholder" type="text" :value="$t('message.select_country_first')" disabled
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] !bg-gray-100">
                    </div>
                    <div class="form-col-12 sm:form-col-6" v-if="props.form.state"><label
                            class="text-sm font-medium capitalize mb-1 field-title">{{
                                $t('label.city') }}</label>
                        <vue-select
                            class="frontend-select w-full h-12 px-4 rounded-lg text-base capitalize border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500 appearance-none"
                            id="city" v-bind:class="errors.city ? 'invalid' : ''" v-model="props.form.city"
                            :options="props.cities" label-by="name" value-by="name" :closeOnSelect="true"
                            :searchable="true" :clearOnClose="true" :disabled="props.cities.length === 0"
                            placeholder="--" search-placeholder="--" />
                        <small class="db-field-alert" v-if="errors.city">
                            {{ errors.city[0] }}
                        </small>
                        <small class="db-field-alert !text-slate-500" v-if="props.cities.length === 0">
                            {{ $t('message.no_cities_available') }}
                        </small>
                    </div>
                    <div class="form-col-12 sm:form-col-6" v-else>
                        <label class="text-sm font-medium capitalize mb-1 field-title" for="city-placeholder">
                            {{ $t('label.city') }}
                        </label>
                        <input id="city-placeholder" type="text"
                            :value="props.form.country ? $t('message.select_state_first') : $t('message.select_country_first')"
                            disabled class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] !bg-gray-100">
                    </div>
                    <div class="form-col-12 sm:form-col-6"><label class="text-sm font-medium capitalize mb-1"
                            for="zip_code">{{
                                $t('label.zip_code') }}
                        </label><input type="text" v-model="props.form.zip_code"
                            :class="errors.zip_code ? 'invalid' : ''"
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500">
                        <small class="db-field-alert" v-if="errors.zip_code">
                            {{ errors.zip_code[0] }}
                        </small>
                    </div>
                    <div class="form-col-12 sm:form-col-6">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="text-sm font-medium capitalize field-title required !mb-0" for="street_address">{{
                                $t('label.street_address') }}</label>
                            <button type="button" @click.prevent="useCurrentLocation"
                                :disabled="isAutoDetectingLocation"
                                :title="$t('button.use_current_location')"
                                class="w-8 h-8 rounded-full flex items-center justify-center bg-[#FFF4F1] text-primary disabled:opacity-60">
                                <i :class="isAutoDetectingLocation ? 'lab-fill-refresh animate-spin' : 'lab-fill-location'"></i>
                            </button>
                        </div>
                        <div class="relative">
                            <input type="text" id="street_address" :class="errors.address ? 'invalid' : ''"
                                v-model="props.form.address" @input="handleAddressInput"
                                @focus="handleAddressInput" @blur="hideSuggestionsWithDelay"
                                class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500">
                            <ul v-if="showAddressSuggestions && addressSuggestions.length > 0"
                                class="absolute top-14 left-0 right-0 z-20 max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl">
                                <li v-for="suggestion in addressSuggestions" :key="suggestion.id">
                                    <button type="button" @click.prevent="selectAddressSuggestion(suggestion)"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100">
                                        {{ suggestion.label }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <small class="db-field-alert !text-slate-500" v-if="isAddressSuggestionLoading">
                            {{ $t('message.loading_suggestions') }}
                        </small>
                        <small class="db-field-alert" v-if="errors.address">
                            {{ errors.address[0] }}
                        </small>
                    </div>
                    <div class="form-col-12 sm:form-col-6">
                        <div class="flex flex-wrap gap-6 mt-2"><button type="submit"
                                class="font-bold text-center h-12 leading-12 px-8 rounded-full whitespace-nowrap bg-primary text-white capitalize">{{
                                    $t('button.add_address')
                                }}</button><button @click="reset()" type="button"
                                class="font-bold text-center h-12 leading-12 px-8 rounded-full whitespace-nowrap bg-[#F7F7FC] capitalize">{{
                                    $t('button.cancel')
                                }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import appService from "../../../../services/appService";
import targetService from "../../../../services/targetService";
import alertService from "../../../../services/alertService";
import LoadingComponent from "../../components/LoadingComponent";
import locationAutocompleteService from "../../../../services/locationAutocompleteService";
import addressLocationDefaultService from "../../../../services/addressLocationDefaultService";
import ENV from "../../../../config/env";

export default {
    name: "AddressComponent",
    components: { LoadingComponent },
    props: ["props"],
    data() {
        return {
            loading: {
                isActive: false,
            },
            errors: {},
            targetID: "address",
            addClass: "modal-active",
            flag: "",
            calling_code: "",
            worldMapData: [],
            mapboxAccessToken: ENV.MAPBOX_ACCESS_TOKEN,
            addressSuggestions: [],
            showAddressSuggestions: false,
            isAddressSuggestionLoading: false,
            autocompleteTimer: null,
            isAutoDetectingLocation: false,
            autoDetectedLocationApplied: false,
            defaultCountryName: null,
            defaultCountryCallingCode: null,
            defaultCountryFlag: "",
        }
    },
    mounted() {
        this.loading.isActive = true;

        Promise.allSettled([
            this.$store.dispatch('frontendCountryStateCity/countries'),
            this.$store.dispatch('frontendCountryCode/lists'),
            this.$store.dispatch('frontendSetting/lists'),
        ]).then(async (results) => {
            await this.applyCurrentProfileDefaults();
            const companyCountryCode = results[2]?.value?.data?.data?.company_country_code || null;
            await this.applyCompanyCountryCodeDefault(companyCountryCode);
            await this.applyIpLocationDefault();
            this.loading.isActive = false;
        });
    },
    computed: {
        addButton: function () {
            return { title: this.$t("button.add_new_address") }
        },
        countryCodes: function () {
            return this.$store.getters['frontendCountryCode/lists'];
        },
        countries: function () {
            return this.$store.getters['frontendCountryStateCity/countries'];
        },
        selectedCountry: function () {
            return this.countries.find((country) => country.name === this.props.form.country) || null;
        },
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        }
    },
    methods: {
        phoneNumber(e) {
            return appService.phoneNumber(e);
        },
        applyCompanyCountryCodeDefault: async function (companyCountryCode) {
            if (!companyCountryCode) {
                return;
            }

            const countryCode = addressLocationDefaultService.findCountryCode(this.countryCodes, companyCountryCode);
            if (countryCode) {
                this.applyPhoneCode(countryCode.calling_code, countryCode.flag_emoji, false);
                return;
            }

            await this.$store.dispatch('frontendCountryCode/show', companyCountryCode).then(res => {
                this.applyPhoneCode(res.data.data.calling_code, res.data.data.flag_emoji, false);
            }).catch(() => {});
        },
        applyIpLocationDefault: async function (options = {}) {
            const defaults = await addressLocationDefaultService.resolve(this.countries, this.countryCodes);
            if (!defaults) {
                return false;
            }

            const force = options.force === true;
            const applyRegionalFields = options.applyRegionalFields === true;
            this.applyPhoneCode(defaults.callingCode, defaults.flagEmoji, force || !this.props.form.country_code);

            if (defaults.country?.name && (force || !this.props.form.country)) {
                this.props.form.country = defaults.country.name;
            }

            const countryName = this.props.form.country || defaults.country?.name;
            if (countryName && (force || !this.props.form.state || !this.props.form.city)) {
                await this.callStates(
                    countryName,
                    applyRegionalFields ? (defaults.state || this.props.form.state) : null,
                    applyRegionalFields ? (defaults.city || this.props.form.city) : null
                );
            }

            if (applyRegionalFields) {
                addressLocationDefaultService.applyLocationDefaults(this.props.form, defaults, {
                    forceAddress: force,
                    applyPostalCode: force,
                    allowApproximateAddress: true,
                });
            }

            this.autoDetectedLocationApplied = false;
            this.rememberDefaultLocation(defaults.country?.name || null, defaults.callingCode, defaults.flagEmoji);
            return true;
        },
        applyCurrentProfileDefaults: async function () {
            const profile = this.authInfo;
            addressLocationDefaultService.applyProfileDefaults(this.props.form, profile);

            if (!profile?.country_code) {
                return;
            }

            const countryCode = addressLocationDefaultService.findCountryCode(this.countryCodes, null, profile.country_code);
            if (countryCode) {
                this.applyPhoneCode(countryCode.calling_code, countryCode.flag_emoji, true);
                return;
            }

            await this.$store.dispatch('frontendCountryCode/callingCode', profile.country_code).then(res => {
                this.applyPhoneCode(res.data.data.calling_code, res.data.data.flag_emoji, true);
            }).catch(() => {});
        },
        applyPhoneCode: function (callingCode, flagEmoji = "", force = true) {
            if (!callingCode || (!force && this.props.form.country_code)) {
                return;
            }

            this.props.form.country_code = callingCode;
            this.calling_code = callingCode;

            if (flagEmoji) {
                this.props.flag = flagEmoji;
                this.flag = flagEmoji;
            }

            this.rememberDefaultLocation(this.defaultCountryName, callingCode, flagEmoji || this.defaultCountryFlag);
        },
        rememberDefaultLocation: function (countryName = null, callingCode = null, flagEmoji = "") {
            if (countryName) {
                this.defaultCountryName = countryName;
            }

            if (callingCode) {
                this.defaultCountryCallingCode = callingCode;
            }

            if (flagEmoji) {
                this.defaultCountryFlag = flagEmoji;
            }
        },
        showTarget: async function () {
            this.$store.dispatch("frontendAddress/reset").then().catch();
            this.$props.props.form = this.blankAddressForm();
            this.$props.props.states = [];
            this.$props.props.cities = [];
            this.addressSuggestions = [];
            this.showAddressSuggestions = false;
            this.autoDetectedLocationApplied = false;
            targetService.showTarget(this.targetID, this.addClass);
            await this.prepareSmartAddressDefaults(true);
        },
        changeCountry: function (e) {
            this.props.flag = e.flag_emoji;
            this.$props.props.form.country_code = e.calling_code;
            this.rememberDefaultLocation(this.props.form.country, e.calling_code, e.flag_emoji);
        },

        callCountry: function () {
            this.$store.dispatch('frontendCountryStateCity/countries');
        },

        handleCountryChange: async function (countryName) {
            this.applyPhoneCodeForCountry(countryName);
            await this.callStates(countryName);
            await this.autofillLocationByCountry(countryName);
        },
        applyPhoneCodeForCountry: function (countryName) {
            const country = this.countries.find((countryOption) => countryOption.name === countryName);
            if (!country?.code) {
                return;
            }

            const countryCode = addressLocationDefaultService.findCountryCode(this.countryCodes, country.code);
            if (countryCode) {
                this.applyPhoneCode(countryCode.calling_code, countryCode.flag_emoji, true);
            }
        },

        callStates: async function (countryName, preferredState = null, preferredCity = null) {
            this.props.form.state = null;
            this.props.cities = [];
            this.props.states = [];
            this.props.form.city = null;
            this.autoDetectedLocationApplied = false;

            if (!countryName) {
                return;
            }

            await this.$store.dispatch('frontendCountryStateCity/statesByCountry', countryName)
                .then(async (res) => {
                    this.props.states = res.data.data;

                    if (preferredState || preferredCity) {
                        const matchedState = this.matchLocationOption(this.props.states, preferredState)
                            || this.matchLocationOption(this.props.states, preferredCity);
                        if (matchedState) {
                            this.props.form.state = matchedState.name;
                            await this.callCities(matchedState.name, preferredCity || preferredState);
                        }
                    }
                })
        },
        useCurrentLocation: async function () {
            const applied = await this.autofillLocationByCountry(this.props.form.country, {
                allowCountryChange: true,
                preserveExisting: false,
            });

            if (!applied) {
                await this.applyIpLocationDefault();
                alertService.error(this.$t("message.current_location_detection_failed"));
            }
        },
        autofillLocationByCountry: async function (countryName, options = {}) {
            if (!countryName && options.allowCountryChange !== true) {
                return false;
            }

            let selectedCountry = countryName
                ? this.countries.find((country) => country.name === countryName)
                : null;
            if (countryName && !selectedCountry?.code) {
                return false;
            }

            this.isAutoDetectingLocation = true;
            try {
                const detectedLocation = await locationAutocompleteService.detectAddressByCountry(
                    this.mapboxAccessToken,
                    options.allowCountryChange === true ? null : selectedCountry.code
                );

                if (!detectedLocation) {
                    return false;
                }

                if (
                    selectedCountry
                    && detectedLocation.country_code
                    && detectedLocation.country_code.toUpperCase() !== selectedCountry.code.toUpperCase()
                ) {
                    if (options.allowCountryChange !== true) {
                        return false;
                    }
                }

                if (
                    detectedLocation.country_code
                    && (!selectedCountry || detectedLocation.country_code.toUpperCase() !== selectedCountry.code.toUpperCase())
                ) {
                    if (options.allowCountryChange !== true) {
                        return false;
                    }

                    const detectedCountry = this.countries.find((country) => {
                        return country.code?.toUpperCase() === detectedLocation.country_code.toUpperCase();
                    });

                    if (!detectedCountry) {
                        return false;
                    }

                    selectedCountry = detectedCountry;
                    countryName = detectedCountry.name;
                    this.props.form.country = detectedCountry.name;
                    this.applyPhoneCodeForCountry(detectedCountry.name);
                }

                if (!countryName) {
                    return false;
                }

                const shouldPreserve = options.preserveExisting === true;
                const detectedAddress = detectedLocation.street_address || detectedLocation.label || "";

                if (!shouldPreserve || !this.props.form.address) {
                    this.props.form.address = detectedAddress || this.props.form.address;
                }

                if (!shouldPreserve || !this.props.form.zip_code) {
                    this.props.form.zip_code = detectedLocation.zip_code || this.props.form.zip_code;
                }

                this.props.form.latitude = detectedLocation.latitude || null;
                this.props.form.longitude = detectedLocation.longitude || null;

                const preferredState = detectedLocation.stored_state || detectedLocation.state || this.props.form.state || null;
                const preferredCity = detectedLocation.stored_city || detectedLocation.city || this.props.form.city || null;
                await this.callStates(countryName, preferredState, preferredCity);
                this.autoDetectedLocationApplied = true;
                return true;
            } catch (error) {
                this.autoDetectedLocationApplied = false;
                return false;
            } finally {
                this.isAutoDetectingLocation = false;
            }
        },
        prepareSmartAddressDefaults: async function (useBrowserLocation = false) {
            await this.applyCurrentProfileDefaults();

            if (useBrowserLocation) {
                const applied = await this.autofillLocationByCountry(this.props.form.country, {
                    allowCountryChange: true,
                    preserveExisting: false,
                });
                if (applied) {
                    return;
                }
            }

            await this.applyIpLocationDefault();
        },
        blankAddressForm: function () {
            return {
                full_name: "",
                email: "",
                country_code: this.defaultCountryCallingCode || this.calling_code,
                phone: "",
                country: this.defaultCountryName,
                state: null,
                city: null,
                zip_code: "",
                address: "",
                latitude: null,
                longitude: null,
            };
        },
        callCities: async function (stateName, preferredCity = null) {
            this.props.form.city = null;
            this.props.cities = [];

            if (!stateName) {
                return;
            }

            await this.$store.dispatch('frontendCountryStateCity/citiesByState', stateName)
                .then((res) => {
                    this.props.cities = res.data.data;
                    if (preferredCity) {
                        const matchedCity = this.matchLocationOption(this.props.cities, preferredCity)
                            || this.matchLocationOption(this.props.cities, stateName)
                            || (this.props.cities.length === 1 ? this.props.cities[0] : null);
                        if (matchedCity) {
                            this.props.form.city = matchedCity.name;
                        }
                    }
                })
        },
        normalizeLocationValue: function (value) {
            const normalized = (value || "")
                .toLowerCase()
                .replace(/[^a-z0-9]/g, "");
            const aliases = {
                chittagong: "chattogram",
                chittagongcity: "chattogram",
                chittagongdistrict: "chattogram",
                chittagongdivision: "chattogram",
                chattagam: "chattogram",
                chattagamcity: "chattogram",
                chattagamdistrict: "chattogram",
                chattagamdivision: "chattogram",
                chattogramcity: "chattogram",
                chattogramdistrict: "chattogram",
                chattogramdivision: "chattogram",
                dhakacity: "dhaka",
                dhakadistrict: "dhaka",
                dhakadivision: "dhaka",
            };

            return aliases[normalized] || normalized.replace(/(division|district|city)$/u, "");
        },
        matchLocationOption: function (options, target) {
            const normalizedTarget = this.normalizeLocationValue(target);
            if (!normalizedTarget) {
                return null;
            }

            return options.find((option) => {
                const normalizedOption = this.normalizeLocationValue(option.name);
                return normalizedOption === normalizedTarget
                    || normalizedOption.includes(normalizedTarget)
                    || normalizedTarget.includes(normalizedOption);
            }) || null;
        },
        handleAddressInput: function () {
            if (!this.mapboxAccessToken) {
                this.addressSuggestions = [];
                this.showAddressSuggestions = false;
                return;
            }

            const query = (this.props.form.address || "").trim();
            if (query.length < 3) {
                this.addressSuggestions = [];
                this.showAddressSuggestions = false;
                return;
            }

            clearTimeout(this.autocompleteTimer);
            this.autocompleteTimer = setTimeout(() => {
                this.fetchAddressSuggestions(query);
            }, 350);
        },
        fetchAddressSuggestions: async function (query) {
            this.isAddressSuggestionLoading = true;
            try {
                const suggestions = await locationAutocompleteService.searchAddressSuggestions(
                    query,
                    this.mapboxAccessToken,
                    this.selectedCountry?.code ?? null
                );

                this.addressSuggestions = suggestions;
                this.showAddressSuggestions = true;
            } catch (error) {
                this.addressSuggestions = [];
                this.showAddressSuggestions = false;
            } finally {
                this.isAddressSuggestionLoading = false;
            }
        },
        hideSuggestionsWithDelay: function () {
            setTimeout(() => {
                this.showAddressSuggestions = false;
            }, 150);
        },
        selectAddressSuggestion: async function (suggestion) {
            this.props.form.address = suggestion.street_address || suggestion.label;
            this.props.form.zip_code = suggestion.zip_code || this.props.form.zip_code;
            this.props.form.latitude = suggestion.latitude || null;
            this.props.form.longitude = suggestion.longitude || null;
            this.showAddressSuggestions = false;
            this.addressSuggestions = [];

            const country = this.countries.find((countryOption) => {
                const countryCodeMatch = suggestion.country_code
                    && countryOption.code.toUpperCase() === suggestion.country_code.toUpperCase();
                if (countryCodeMatch) {
                    return true;
                }

                return this.normalizeLocationValue(countryOption.name) === this.normalizeLocationValue(suggestion.country);
            });

            if (!country) {
                return;
            }

            this.props.form.country = country.name;
            this.applyPhoneCodeForCountry(country.name);
            await this.callStates(country.name, suggestion.state, suggestion.city);
        },
        reset: function () {
            targetService.hideTarget(this.targetID, this.addClass);
            this.$store.dispatch("frontendAddress/reset").then().catch();
            this.errors = {};
            this.$props.props.form = this.blankAddressForm();
            this.applyCurrentProfileDefaults().then().catch();
            this.$props.props.flag = this.defaultCountryFlag || this.flag;
            this.$props.props.states = [];
            this.$props.props.cities = [];
            this.addressSuggestions = [];
            this.showAddressSuggestions = false;
            this.autoDetectedLocationApplied = false;
            if (this.defaultCountryName) {
                this.callStates(this.defaultCountryName).then().catch();
            }
        },
        save: function () {
            try {
                const tempId = this.$store.getters["frontendAddress/temp"].temp_id;
                this.loading.isActive = true;
                this.$store.dispatch("frontendAddress/save", this.props).then((res) => {
                    targetService.hideTarget(this.targetID, this.addClass);
                    this.loading.isActive = false;
                    alertService.successFlip(tempId === null ? 0 : 1, this.$t("label.address"));
                    this.props.form = this.blankAddressForm();
                    this.applyCurrentProfileDefaults().then().catch();
                    this.$props.props.flag = this.defaultCountryFlag || this.flag;
                    this.$props.props.states = [];
                    this.$props.props.cities = [];
                    this.addressSuggestions = [];
                    this.showAddressSuggestions = false;
                    this.autoDetectedLocationApplied = false;
                    this.errors = {};
                    if (this.defaultCountryName) {
                        this.callStates(this.defaultCountryName).then().catch();
                    }
                }).catch((err) => {
                    this.loading.isActive = false;
                    this.errors = err.response.data.errors;
                });
            } catch (err) {
                this.loading.isActive = false;
                alertService.error(err);
            }
        },
    }
}
</script>
