<template>
    <LoadingComponent :props="loading" />
    <div v-if="show" class="mb-6 rounded-2xl shadow-card">
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-gray-100">
            <h4 class="font-bold capitalize">{{ title }}</h4>
            <div class="flex flex-wrap items-center gap-4">
                <button v-if="hasSelectedAddress" type="button"
                    @click.prevent="edit(selectedAddress)"
                    class="px-3 h-8 leading-8 rounded-full flex items-center gap-2 bg-[#E6FFF0] text-success">
                    <i class="lab-fill-edit"></i>
                    <span class="text-sm font-medium capitalize whitespace-nowrap">{{ $t('button.edit') }}</span>
                </button>
                <button type="button" @click.prevent="showTarget(slug + '-address-modal', 'modal-active')"
                    class="px-3 h-8 leading-8 rounded-full flex items-center gap-2 bg-[#FFF4F1] text-primary">
                    <i class="lab-fill-circle-plus"></i>
                    <span class="text-sm font-medium capitalize whitespace-nowrap">{{ $t('button.add_new') }}</span>
                </button>
            </div>
        </div>
        <div v-if="addresses.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-6 p-4">
            <div :class="isSelectedAddress(address) ? 'border-primary/50 bg-[#FFF4F1]' : 'border-[#F7F7F7] bg-[#F7F7F7]'"
                @click.prevent="activeAddress(address)" v-for="address in addresses" :key="address.id"
                class="py-3 px-4 rounded-lg cursor-pointer border transition-all duration-300">
                <span class="text-base font-medium capitalize mb-1">{{ address.full_name }}</span>
                <span v-if="address.phone" class="block text-sm leading-6">{{
                    address.country_code ?? ''
                    }} {{ address.phone }},</span>
                <span v-if="address.email" class="block text-sm leading-6">{{ address.email }},</span>
                <span v-if="address.state" class="block text-sm leading-6">{{ address.state }},</span>
                <span v-if="address.city" class="block text-sm leading-6">{{ address.city }},</span>
                <span v-if="address.country" class="block text-sm leading-6">{{ address.country }},</span>
                <span v-if="address.address" class="block text-sm leading-6">{{ address.address }}<span
                        v-if="address.zip_code">,</span></span>
                <span v-if="address.zip_code" class="block text-sm leading-6">{{ address.zip_code }}</span>
            </div>
        </div>
        <div v-else class="p-4">
            <button type="button" @click.prevent="showTarget(slug + '-address-modal', 'modal-active')"
                class="w-full text-left py-4 px-5 rounded-lg border border-dashed border-[#D9DBE9] bg-[#F7F7FC] transition-all duration-300 hover:border-primary/40 hover:bg-[#FFF4F1]">
                <span class="block text-sm font-semibold text-secondary">
                    {{ addressListError ? $t('message.addresses_could_not_be_loaded') : $t('message.no_saved_address') }}
                </span>
                <span class="block mt-1 text-sm leading-6 text-[#6E7191]">
                    {{ addressListError || $t('message.add_address_to_continue_checkout') }}
                </span>
            </button>
        </div>
    </div>

    <div :id="slug + '-address-modal'"
        class="fixed inset-0 z-50 p-3 w-screen h-dvh overflow-y-auto bg-black/50 transition-all duration-300 opacity-0 invisible">
        <div class="w-full rounded-xl mx-auto bg-white transition-all duration-300 max-w-3xl">
            <div class="flex items-center justify-between gap-2 py-4 px-4 border-b border-slate-100">
                <h3 class="text-lg font-bold capitalize">{{ $t('label.address') }}</h3>
                <button @click.prevent="reset" type="button"
                    class="lab-line-circle-cross text-lg text-[#E93C3C]"></button>
            </div>
            <form class="w-full p-5" @submit.prevent="save">
                <div class="form-row">
                    <div class="form-col-12 sm:form-col-6">
                        <label for="full_name" class="text-sm font-medium capitalize mb-1 field-title required">
                            {{ $t('label.full_name') }}
                        </label>
                        <input type="text" v-model="address.form.full_name" :class="errors.full_name ? 'invalid' : ''"
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500">
                        <small class="db-field-alert" v-if="errors.full_name">
                            {{ errors.full_name[0] }}
                        </small>
                    </div>

                    <div class="form-col-12 sm:form-col-6">
                        <label for="email" class="text-sm font-medium capitalize mb-1 field-title">
                            {{ $t("label.email") }}
                        </label>
                        <input type="email" v-model="address.form.email" :class="errors.email ? 'invalid' : ''"
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500">
                        <small class="db-field-alert" v-if="errors.email">
                            {{ errors.email[0] }}
                        </small>
                    </div>

                    <div class="form-col-12 sm:form-col-6">
                        <label for="phone" class="text-sm font-medium capitalize mb-1 field-title required">
                            {{ $t("label.phone") }}
                        </label>
                        <div :class="errors.phone ? 'invalid' : ''" class="field-control flex items-center">
                            <div class="w-fit flex-shrink-0 dropdown-group">
                                <button type="button" class="flex items-center gap-1 dropdown-btn">
                                    {{ address.flag }}
                                    <span class="whitespace-nowrap flex-shrink-0 text-xs">
                                        {{ address.form.country_code }}
                                    </span>
                                    <i class="fa-solid fa-caret-down text-xs"></i>
                                </button>
                                <ul
                                    class="p-1.5 w-24 rounded-lg shadow-xl absolute top-8 -left-4 z-10 border border-gray-200 bg-white scale-y-0 origin-top dropdown-list !h-52 !overflow-x-hidden !overflow-y-auto thin-scrolling">
                                    <li v-for="countryCode in countryCodes" @click.prevent="changeCountry(countryCode)"
                                        class="flex items-center gap-2 p-1.5 rounded-md cursor-pointer hover:bg-gray-100">
                                        {{ countryCode.flag_emoji }}
                                        <span class="whitespace-nowrap text-xs">{{ countryCode.calling_code }}</span>
                                    </li>
                                </ul>
                            </div>
                            <input v-model="address.form.phone" v-on:keypress="phoneNumber($event)"
                                :class="errors.phone ? 'invalid' : ''" type="text" id="phone"
                                class="pl-2 text-sm w-full h-full" />
                        </div>

                        <small class="db-field-alert" v-if="errors.phone">
                            {{ errors.phone[0] }}
                        </small>
                    </div>

                    <div class="form-col-12 sm:form-col-6">
                        <label class="text-sm font-medium capitalize mb-1 field-title required" for="country">
                            {{ $t('label.country') }}
                        </label>
                        <vue-select
                            class="w-full h-12 px-4 rounded-lg text-base capitalize border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500 appearance-none"
                            id="country" :class="errors.country ? 'invalid' : ''" v-model="address.form.country"
                            @update:modelValue="handleCountryChange($event)" :options="countries" label-by="name" value-by="name"
                            :closeOnSelect="true" :searchable="true" :clearOnClose="true" placeholder="--"
                            search-placeholder="--" />
                        <small class="db-field-alert" v-if="errors.country">
                            {{ errors.country[0] }}
                        </small>
                        <small class="db-field-alert !text-slate-500"
                            v-if="selectedCountryCurrencyCode || selectedCountryCurrencySymbol">
                            {{ $t('label.currency_symbol') }}: {{ selectedCountryCurrencySymbol || '--' }} |
                            {{ $t('label.currency_iso_code') }}: {{ selectedCountryCurrencyCode || '--' }}
                        </small>
                        <small class="db-field-alert !text-slate-500" v-if="isAutoDetectingLocation">
                            {{ $t('message.detecting_current_location') }}
                        </small>
                        <small class="db-field-alert !text-green-600" v-else-if="autoDetectedLocationApplied">
                            {{ $t('message.location_auto_filled_editable') }}
                        </small>
                    </div>

                    <div class="form-col-12 sm:form-col-6" v-if="address.form.country">
                        <label class="text-sm font-medium capitalize mb-1 field-title" for="state">
                            {{ $t('label.state') }}
                        </label>
                        <vue-select
                            class="w-full h-12 px-4 rounded-lg text-base capitalize border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500 appearance-none"
                            id="state" v-bind:class="errors.state ? 'invalid' : ''" v-model="address.form.state"
                            @update:modelValue="callCities($event)" :options="address.states" label-by="name"
                            value-by="name" :closeOnSelect="true" :searchable="true" :clearOnClose="true"
                            :disabled="address.states.length === 0"
                            placeholder="--" search-placeholder="--" />
                        <small class="db-field-alert" v-if="errors.state">
                            {{ errors.state[0] }}
                        </small>
                        <small class="db-field-alert !text-slate-500" v-if="address.states.length === 0">
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

                    <div class="form-col-12 sm:form-col-6" v-if="address.form.state">
                        <label class="text-sm font-medium capitalize mb-1 field-title">
                            {{ $t('label.city') }}
                        </label>
                        <vue-select
                            class="w-full h-12 px-4 rounded-lg text-base capitalize border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500 appearance-none"
                            id="city" v-bind:class="errors.city ? 'invalid' : ''" v-model="address.form.city"
                            :options="address.cities" label-by="name" value-by="name" :closeOnSelect="true"
                            :searchable="true" :clearOnClose="true" :disabled="address.cities.length === 0"
                            placeholder="--" search-placeholder="--" />
                        <small class="db-field-alert" v-if="errors.city">
                            {{ errors.city[0] }}
                        </small>
                        <small class="db-field-alert !text-slate-500" v-if="address.cities.length === 0">
                            {{ $t('message.no_cities_available') }}
                        </small>
                    </div>
                    <div class="form-col-12 sm:form-col-6" v-else>
                        <label class="text-sm font-medium capitalize mb-1 field-title" for="city-placeholder">
                            {{ $t('label.city') }}
                        </label>
                        <input id="city-placeholder" type="text"
                            :value="address.form.country ? $t('message.select_state_first') : $t('message.select_country_first')"
                            disabled class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] !bg-gray-100">
                    </div>

                    <div class="form-col-12 sm:form-col-6">
                        <label class="text-sm font-medium capitalize mb-1" for="zip_code">
                            {{ $t('label.zip_code') }}
                        </label>
                        <input type="text" v-model="address.form.zip_code" :class="errors.zip_code ? 'invalid' : ''"
                            class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500">
                        <small class="db-field-alert" v-if="errors.zip_code">
                            {{ errors.zip_code[0] }}
                        </small>
                    </div>

                    <div class="form-col-12 sm:form-col-6">
                        <label class="text-sm font-medium capitalize mb-1 field-title required" for="street_address">
                            {{ $t('label.street_address') }}
                        </label>
                        <div class="relative">
                            <input type="text" id="street_address" :class="errors.address ? 'invalid' : ''"
                                v-model="address.form.address" @input="handleAddressInput" @focus="handleAddressInput"
                                @blur="hideSuggestionsWithDelay"
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
                        <div class="flex flex-wrap gap-6 mt-2">
                            <button type="submit"
                                class="font-bold text-center h-12 leading-12 px-8 rounded-full whitespace-nowrap bg-primary text-white capitalize">
                                {{ $t('button.save_address') }}
                            </button>

                            <button @click.prevent="reset" type="button"
                                class="font-bold text-center h-12 leading-12 px-8 rounded-full whitespace-nowrap bg-[#F7F7FC] capitalize">
                                {{ $t('button.cancel') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>


<script>
import orderTypeEnum from "../../../../enums/modules/orderTypeEnum";
import appService from "../../../../services/appService";
import targetService from "../../../../services/targetService";
import alertService from "../../../../services/alertService";
import LoadingComponent from "../../components/LoadingComponent.vue";
import locationAutocompleteService from "../../../../services/locationAutocompleteService";
import ENV from "../../../../config/env";


export default {
    name: "AddressComponent",
    props: {
        "show": { type: Boolean, Default: false },
        "slug": { type: String, Default: "shipping" },
        "title": { type: String },
        "selectedAddress": { type: Object },
        "method": { type: Function }
    },
    data() {
        return {
            loading: {
                isActive: false
            },
            orderTypeEnum: orderTypeEnum,
            address: {
                form: {
                    full_name: "",
                    email: "",
                    country_code: null,
                    phone: "",
                    country: null,
                    state: null,
                    city: null,
                    zip_code: "",
                    address: "",
                    latitude: null,
                    longitude: null,
                },
                search: {
                    paginate: 0,
                    order_column: "id",
                    order_type: "asc",

                },
                flag: "",
                calling_code: "",
                states: [],
                cities: []
            },
            worldMapData: [],
            activeAddressId: null,
            errors: {},
            mapboxAccessToken: ENV.MAPBOX_ACCESS_TOKEN,
            addressSuggestions: [],
            showAddressSuggestions: false,
            isAddressSuggestionLoading: false,
            autocompleteTimer: null,
            isAutoDetectingLocation: false,
            autoDetectedLocationApplied: false,
            addressListError: "",
        }
    },
    components: {
        LoadingComponent
    },
    computed: {
        addresses: function () {
            const lists = this.$store.getters["frontendAddress/lists"];
            return Array.isArray(lists) ? lists : [];
        },
        hasSelectedAddress: function () {
            return this.hasAddress(this.selectedAddress);
        },
        countryCodes: function () {
            return this.$store.getters['frontendCountryCode/lists'];
        },
        countries: function () {
            return this.$store.getters['frontendCountryStateCity/countries'];
        },
        selectedCountry: function () {
            return this.countries.find((country) => country.name === this.address.form.country) || null;
        },
        selectedCountryCurrencyCode: function () {
            return this.selectedCountry?.currency_code ?? null;
        },
        selectedCountryCurrencySymbol: function () {
            return this.selectedCountry?.currency_symbol ?? null;
        }
    },
    watch: {
        addresses: {
            immediate: true,
            handler: function (addresses) {
                this.selectDefaultAddress(addresses);
            }
        },
        show: function (value) {
            if (value) {
                this.selectDefaultAddress();
            }
        },
        selectedAddress: {
            deep: true,
            handler: function (address) {
                if (this.hasAddress(address)) {
                    this.activeAddressId = address.id;
                }
            }
        }
    },
    mounted() {
        setTimeout(() => {
            this.callCountry();
        }, 300);
        this.listAddresses();

        this.loading.isActive = true;
        this.$store.dispatch('frontendCountryCode/lists');
        this.$store.dispatch('company/lists').then(companyRes => {
            this.$store.dispatch('frontendCountryCode/show', companyRes.data.data.company_country_code).then(res => {
                this.address.form.country_code = res.data.data.calling_code;
                this.address.calling_code = res.data.data.calling_code;
                this.address.flag = res.data.data.flag_emoji;
                this.loading.isActive = false;
            }).catch((err) => {
                this.loading.isActive = false;
            });
        }).catch((err) => {
            this.loading.isActive = false;
        });
    },
    methods: {
        phoneNumber(e) {
            return appService.phoneNumber(e);
        },
        listAddresses: function () {
            this.loading.isActive = true;
            this.addressListError = "";
            this.$store.dispatch("frontendAddress/lists", {
                search: {
                    paginate: 0,
                    order_column: "id",
                    order_type: "asc",
                }
            }).then(() => {
                this.loading.isActive = false;
                this.selectDefaultAddress();
            }).catch((err) => {
                this.loading.isActive = false;
                this.addressListError = err?.response?.data?.message || this.$t("error.something_wrong");
            });
        },
        hasAddress: function (address) {
            return address && typeof address === "object" && Object.keys(address).length > 0;
        },
        isSelectedAddress: function (address) {
            return this.hasSelectedAddress && address.id === this.selectedAddress.id;
        },
        selectDefaultAddress: function (addresses = this.addresses) {
            if (!this.show || this.hasSelectedAddress || !Array.isArray(addresses) || addresses.length === 0) {
                return;
            }

            this.activeAddress(addresses[0]);
        },
        activeAddress: function (address) {
            this.activeAddressId = address.id;
            this.method(address);
        },
        showTarget: function (targetID, addClass) {
            targetService.showTarget(targetID, addClass);
        },
        callCountry: function () {
            this.$store.dispatch('frontendCountryStateCity/countries');
        },
        changeCountry: function (e) {
            this.address.flag = e.flag_emoji;
            this.address.form.country_code = e.calling_code;
        },
        handleCountryChange: async function (countryName) {
            await this.callStates(countryName);
            await this.autofillLocationByCountry(countryName);
        },
        callStates: async function (countryName, preferredState = null, preferredCity = null) {
            this.address.form.state = null;
            this.address.cities = [];
            this.address.states = [];
            this.address.form.city = null;
            this.autoDetectedLocationApplied = false;

            if (!countryName) {
                return;
            }

            await this.$store.dispatch('frontendCountryStateCity/statesByCountry', countryName)
                .then(async (res) => {
                    this.address.states = res.data.data;
                    if (preferredState) {
                        const matchedState = this.matchLocationOption(this.address.states, preferredState);
                        if (matchedState) {
                            this.address.form.state = matchedState.name;
                            await this.callCities(matchedState.name, preferredCity);
                        }
                    }
                })
        },
        autofillLocationByCountry: async function (countryName) {
            if (!countryName || !this.mapboxAccessToken) {
                return;
            }

            const selectedCountry = this.countries.find((country) => country.name === countryName);
            if (!selectedCountry?.code) {
                return;
            }

            this.isAutoDetectingLocation = true;
            try {
                const detectedLocation = await locationAutocompleteService.detectAddressByCountry(
                    this.mapboxAccessToken,
                    selectedCountry.code
                );

                if (!detectedLocation) {
                    return;
                }

                if (
                    detectedLocation.country_code
                    && detectedLocation.country_code.toUpperCase() !== selectedCountry.code.toUpperCase()
                ) {
                    return;
                }

                this.address.form.address = detectedLocation.street_address || this.address.form.address;
                this.address.form.zip_code = detectedLocation.zip_code || this.address.form.zip_code;
                this.address.form.latitude = detectedLocation.latitude || null;
                this.address.form.longitude = detectedLocation.longitude || null;

                const preferredState = detectedLocation.state || null;
                const preferredCity = detectedLocation.city || null;
                await this.callStates(countryName, preferredState, preferredCity);
                this.autoDetectedLocationApplied = true;
            } catch (error) {
                this.autoDetectedLocationApplied = false;
            } finally {
                this.isAutoDetectingLocation = false;
            }
        },
        callCities: async function (stateName, preferredCity = null) {
            this.address.form.city = null;
            this.address.cities = [];

            if (!stateName) {
                return;
            }

            await this.$store.dispatch('frontendCountryStateCity/citiesByState', stateName)
                .then((res) => {
                    this.address.cities = res.data.data;
                    if (preferredCity) {
                        const matchedCity = this.matchLocationOption(this.address.cities, preferredCity);
                        if (matchedCity) {
                            this.address.form.city = matchedCity.name;
                        }
                    }
                })
        },
        normalizeLocationValue: function (value) {
            return (value || "")
                .toLowerCase()
                .replace(/[^a-z0-9]/g, "");
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

            const query = (this.address.form.address || "").trim();
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
            this.address.form.address = suggestion.street_address || suggestion.label;
            this.address.form.zip_code = suggestion.zip_code || this.address.form.zip_code;
            this.address.form.latitude = suggestion.latitude || null;
            this.address.form.longitude = suggestion.longitude || null;
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

            this.address.form.country = country.name;
            await this.callStates(country.name, suggestion.state, suggestion.city);
        },
        reset: function () {
            targetService.hideTarget(this.slug + '-address-modal', 'modal-active');
            this.$store.dispatch("frontendAddress/reset").then().catch();
            this.errors = {};
            this.address.form = {
                full_name: "",
                email: "",
                country_code: this.address.calling_code,
                phone: "",
                country: null,
                state: null,
                city: null,
                zip_code: "",
                address: "",
                latitude: null,
                longitude: null,
            };
            this.address.states = [];
            this.address.cities = [];
            this.addressSuggestions = [];
            this.showAddressSuggestions = false;
            this.autoDetectedLocationApplied = false;
        },
        save: function () {
            try {
                const tempId = this.$store.getters["frontendAddress/temp"].temp_id;
                this.loading.isActive = true;
                this.$store.dispatch("frontendAddress/save", this.address).then((res) => {
                    targetService.hideTarget(this.slug + '-address-modal', 'modal-active');
                    this.loading.isActive = false;
                    alertService.successFlip(tempId === null ? 0 : 1, this.$t("label.address"));
                    this.address.form = {
                        full_name: "",
                        email: "",
                        country_code: this.address.calling_code,
                        phone: "",
                        country: null,
                        state: null,
                        city: null,
                        zip_code: "",
                        address: "",
                        latitude: null,
                        longitude: null,
                    };
                    this.address.states = [];
                    this.address.cities = [];
                    this.addressSuggestions = [];
                    this.showAddressSuggestions = false;
                    this.autoDetectedLocationApplied = false;
                    this.errors = {};
                    this.activeAddress(res.data.data);
                }).catch((err) => {
                    this.loading.isActive = false;
                    this.errors = err.response.data.errors;
                });
            } catch (err) {
                this.loading.isActive = false;
                alertService.error(err);
            }
        },
        edit: function (address) {
            if (this.hasSelectedAddress) {
                targetService.showTarget(this.slug + '-address-modal', 'modal-active');
                this.loading.isActive = true;
                this.$store.dispatch("frontendAddress/edit", address.id).then(async (res) => {
                    this.loading.isActive = false;

                    if (address.state !== "") {
                        await this.$store.dispatch('frontendCountryStateCity/statesByCountry', address.country)
                            .then((res) => {
                                this.address.states = res.data.data;
                            })
                        await this.$store.dispatch('frontendCountryStateCity/citiesByState', address.state)
                            .then((res) => {
                                this.address.cities = res.data.data;
                            })

                        if (address.city === "") {
                            this.address.form.city = null;
                        }
                    } else {
                        await this.$store.dispatch('frontendCountryStateCity/statesByCountry', address.country)
                            .then((res) => {
                                this.address.states = res.data.data;
                            })
                        this.address.form.state = null;
                        this.address.form.city = null;
                    }

                    this.address.form = {
                        full_name: address.full_name,
                        email: address.email,
                        country_code: address.country_code,
                        phone: address.phone,
                        country: address.country,
                        state: address.state,
                        city: address.city,
                        zip_code: address.zip_code,
                        address: address.address,
                        latitude: address.latitude,
                        longitude: address.longitude,
                    };

                    if (address.state === "") {
                        this.address.form.state = null;
                    }

                    if (address.city === "") {
                        this.address.form.city = null;
                    }

                    this.$store.dispatch('frontendCountryCode/callingCode', address.country_code).then(res => {
                        this.address.flag = res.data.data.flag_emoji;
                        this.loading.isActive = false;
                    }).catch((err) => {
                        this.loading.isActive = false;
                    });
                }).catch((err) => {
                    alertService.error(err.response.data.message);
                });
            }
        }
    }
}
</script>
