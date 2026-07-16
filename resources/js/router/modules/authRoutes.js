const LoginComponent = () => import("../../components/frontend/auth/LoginComponent");
const SignupComponent = () => import("../../components/frontend/auth/SignupComponent.vue");
const MerchantRegisterComponent = () => import("../../components/frontend/auth/MerchantRegisterComponent.vue");
const ForgotPasswordComponent = () => import("../../components/frontend/auth/ForgotPasswordComponent");
const ForgotPasswordVerifyComponent = () => import("../../components/frontend/auth/ForgotPasswordVerifyComponent");
const ResetPasswordComponent = () => import("../../components/frontend/auth/ResetPasswordComponent");
const SignupVerifyComponent = () => import("../../components/frontend/auth/SignupVerifyComponent");

export default [
    {
        path: '/admin/login',
        component: LoginComponent,
        name: 'auth.adminLogin',
        meta: {
            isFrontend: false,
            auth: false,
            authContext: 'admin'
        }
    },
    {
        path: '/register',
        component: MerchantRegisterComponent,
        name: 'auth.merchantRegister',
        meta: {
            isFrontend: false,
            auth: false,
            authContext: 'merchant'
        }
    },
    {
        path: '/login',
        component: LoginComponent,
        name: 'auth.login',
        meta: {
            isFrontend: true,
            auth: false,
            authContext: 'customer'
        }
    },
    {
        path: '/signup',
        component: SignupComponent,
        name: 'auth.signup',
        meta: {
            isFrontend: true,
            auth: false
        }
    },
    {
        path: '/signup/verify',
        component: SignupVerifyComponent,
        name: 'auth.signupVerify',
        meta: {
            isFrontend: true,
            auth: false
        }
    },
    {
        path: '/forgot-password',
        component: ForgotPasswordComponent,
        name: 'auth.forgotPassword',
        meta: {
            isFrontend: true,
            auth: false
        }
    },
    {
        path: '/forgot-password/verify',
        name: 'auth.forgotPasswordVerify',
        component: ForgotPasswordVerifyComponent,
        meta: {
            isFrontend: true,
            auth: false
        }
    },
    {
        path: '/forgot-password/reset-password',
        name: 'auth.resetPassword',
        component: ResetPasswordComponent,
        meta: {
            isFrontend: true,
            auth: false
        }
    },

];
