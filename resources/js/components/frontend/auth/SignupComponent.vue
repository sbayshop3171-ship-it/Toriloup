<template>
    <LoadingComponent :props="loading" />
    <section class="w-full min-h-[calc(100vh_-_160px)] flex items-center justify-center px-4 py-8 sm:py-10"
        dir="ltr">
        <div class="w-full max-w-3xl rounded-2xl flex overflow-hidden gap-y-6 bg-white shadow-card" dir="ltr">
            <img :src="APP_URL + '/images/required/auth.jpg'" alt="banners"
                class="w-full hidden sm:block sm:max-w-xs md:max-w-sm flex-shrink-0" loading="lazy">
        <form class="w-full p-6" dir="ltr" @submit.prevent="signup">
            <div class="text-center mb-8">
                <h3 class="capitalize text-2xl mb-2 font-bold text-primary">{{ $t('label.sign_up') }}</h3>
            </div>
            <div class="mb-6">
                <label for="formName" class="text-sm font-medium capitalize mb-1 field-title required">{{
                    $t('label.name') }}</label>
                <input v-model="form.name" :class="errors.name ? 'invalid' : ''" id="formName" type="text" dir="ltr"
                    class="w-full h-12 px-4 rounded-lg text-base text-left border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.name">{{ errors.name[0] }}</small>
            </div>

            <div :class="!toggleValue ? 'mb-6' : ''">
                <div class="flex items-center justify-between">
                    <label :for="!toggleValue ? 'formEmail' : 'phone'"
                        class="text-sm font-medium capitalize mb-1 field-title required">{{ inputLabel
                        }}</label>
                    <button type="button" class="text-sm font-medium capitalize mb-1 underline text-primary"
                        @click="handleField()">{{ inputButton }}</button>
                </div>
                <div v-if="toggleValue" :class="errors.phone ? 'invalid' : ''"
                    class="flex items-center gap-1.5 px-4 h-12 rounded-lg border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500">
                    <div class="w-fit flex-shrink-0 dropdown-group">
                        <button type="button" class="flex items-center gap-1 dropdown-btn">
                            {{ flag }}
                            <span class="whitespace-nowrap flex-shrink-0 text-xs">{{ form.country_code
                            }}</span>
                            <i class="fa-solid fa-caret-down text-xs"></i>
                        </button>
                        <ul
                            class="p-1.5 w-24 rounded-lg shadow-xl absolute top-8 -left-4 z-10 border border-gray-200 bg-white scale-y-0 origin-top dropdown-list !h-52 !overflow-x-hidden !overflow-y-auto thin-scrolling">
                            <li v-for="countryCode in countryCodes" @click="countryCodeChange(countryCode)"
                                class="flex items-center gap-2 p-1.5 rounded-md cursor-pointer hover:bg-gray-100">
                                {{ countryCode.flag_emoji }}
                                <span class="whitespace-nowrap text-xs">{{ countryCode.calling_code }}</span>
                            </li>
                        </ul>

                    </div>
                    <input v-model="form.phone" v-on:keypress="phoneNumber($event)" v-bind:class="errors.phone
                        ? 'invalid' : ''" type="text" id="phone" dir="ltr" class="pl-2 text-sm text-left w-full h-full" />
                </div>
                <input v-if="!toggleValue" v-model="form.email" :class="errors.email ? 'invalid' : ''" id="formEmail"
                    type="email" dir="ltr"
                    class="w-full h-12 px-4 rounded-lg text-base text-left border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.email_or_phone">{{ errors.email_or_phone }}</small>
                <span v-else>
                    <small class="db-field-alert" v-if="errors.phone && toggleValue">{{ errors.phone[0] }}</small>
                    <small class="db-field-alert" v-if="errors.email && !toggleValue">{{ errors.email[0] }}</small>
                </span>
            </div>

            <div class="mb-6">
                <label for="formPassword" class="text-sm font-medium capitalize mb-1 field-title required">{{
                    $t('label.password') }}</label>
                <input v-model="form.password" :class="errors.password ? 'invalid' : ''" id="formPassword"
                    type="password" dir="ltr"
                    class="w-full h-12 px-4 rounded-lg text-base text-left border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.password">{{ errors.password[0] }}</small>
            </div>
            <button type="submit"
                class="font-bold text-center w-full h-12 leading-12 rounded-full bg-primary text-white capitalize mb-6">
                {{ $t('label.sign_up') }}
            </button>
            <div class="flex items-center justify-center gap-1.5">
                <span class="font-medium text-text">{{ $t('message.already_have_account') }}</span>
                <router-link class="capitalize font-bold text-primary" :to="{ name: 'auth.login' }">
                    {{ $t('label.sign_in') }}
                </router-link>
            </div>
        </form>
        </div>
    </section>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
import appService from "../../../services/appService";
import ENV from "../../../config/env";
import askEnum from "../../../enums/modules/askEnum"
import alertService from "../../../services/alertService";
import router from "../../../router";
export default {
    name: "LoginComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            form: {
                name: "",
                email: "",
                phone: "",
                country_code: "",
                password: ""
            },
            flag: "",
            errors: {},
            demo: ENV.DEMO,
            APP_URL: ENV.API_URL,
            toggleValue: false,
            inputLabel: this.$t('label.email'),
            inputButton: this.$t('label.use_phone_instead')
        }
    },
    computed: {
        countryCodes: function () {
            return this.$store.getters['frontendCountryCode/lists'];
        },
        setting: function () {
            return this.$store.getters['frontendSetting/lists'];
        },
        carts: function () {
            return this.$store.getters['frontendCart/lists'];
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch('frontendCountryCode/lists');
        this.$store.dispatch('frontendSetting/lists').then(res => {
            this.$store.dispatch('frontendCountryCode/show', res.data.data.company_country_code).then(res => {
                this.form.country_code = res.data.data.calling_code;
                this.flag = res.data.data.flag_emoji;

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
        handleField: function () {
            this.toggleValue = !this.toggleValue

            if (this.toggleValue) {
                this.form.email = "";
                this.inputLabel = this.$t('label.phone');
                this.inputButton = this.$t('label.use_email_instead');
            }
            else {
                this.form.phone = "";
                this.inputLabel = this.$t('label.email');
                this.inputButton = this.$t('label.use_phone_instead');
            }
        },
        countryCodeChange: function (e) {
            this.flag = e.flag_emoji;
            this.form.country_code = e.calling_code;
        },
        isDemoMode: function () {
            return ['true', 'TRUE', 'True', '1', 1, true].includes(this.demo);
        },
        verificationEnabled: function (value) {
            return Number(value) === askEnum.YES;
        },
        sendPhoneOtp: function () {
            this.$store.dispatch("frontendSignup/otpPhone", this.form).then((res) => {
                if (res.data.verification === false) {
                    this.saveUser();
                    return;
                }

                this.loading.isActive = false;
                alertService.success(res.data.message, 'bottom-center');
                this.$router.push({ name: "auth.signupVerify" });
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response.data.message);
            });
        },
        sendEmailOtp: function () {
            this.$store.dispatch("frontendSignup/otpEmail", this.form).then((res) => {
                if (res.data.verification === false) {
                    this.saveUser();
                    return;
                }

                this.loading.isActive = false;
                alertService.success(res.data.message, 'bottom-center');
                this.$router.push({ name: "auth.signupVerify" });
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response.data.message);
            });
        },
        saveUser: function () {
            this.$store.dispatch("frontendSignup/signup", this.form).then((res) => {
                this.loading.isActive = false;
                this.$store.dispatch("signupLoginVerify", this.form).then((res) => {
                    this.loading.isActive = false;
                    alertService.success(res.data.message, 'bottom-center');
                    this.$store.dispatch("frontendSignup/reset");
                    this.$router.push({
                        name: "frontend.home",
                    });
                }).catch((err) => {
                    this.loading.isActive = false;
                    this.errors = err.response.data.message;
                });
                this.$router.push({ name: "frontend.home" });
                this.form = {
                    name: "",
                    email: "",
                    phone: "",
                    code: "",
                    password: ""
                };
                this.errors = {};
            }).catch((err) => {
                this.loading.isActive = false;
                this.errors = err.response.data.errors;
            })
        },
        signup: function () {
            try {


                this.loading.isActive = true;
                this.$store.dispatch("frontendSignup/signupValidation", this.form).then((res) => {
                    this.$store.dispatch('frontendSetting/lists').finally(() => {
                        if (this.verificationEnabled(this.setting.site_phone_verification) && this.form.phone !== "" && !this.isDemoMode()) {
                            this.sendPhoneOtp();
                        } else if (this.verificationEnabled(this.setting.site_email_verification) && this.form.email !== "" && !this.isDemoMode()) {
                            this.sendEmailOtp();
                        } else {
                            this.saveUser();
                        }
                    });
                }).catch((err) => {
                    this.loading.isActive = false;
                    this.errors = err.response.data.errors;
                })
            } catch (err) {
                this.loading.isActive = false;
            }
        },
    }
}
</script>
