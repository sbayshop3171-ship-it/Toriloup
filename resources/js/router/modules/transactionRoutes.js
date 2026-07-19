const TransactionListComponent = () => import("../../components/admin/transactions/TransactionListComponent");
const MerchantWalletComponent = () => import("../../components/admin/wallet/MerchantWalletComponent.vue");
const PlatformWalletComponent = () => import("../../components/platform/PlatformWalletComponent.vue");

export default [
    {
        path: '/admin/transactions',
        component: TransactionListComponent,
        name: 'admin.transactions.list',
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: 'transactions',
            breadcrumb: 'transactions'
        }
    },
    {
        path: '/admin/wallet',
        component: MerchantWalletComponent,
        name: 'merchant.wallet',
        meta: {
            isFrontend: false,
            auth: true,
            workspace: 'merchant',
            permissionUrl: 'transactions',
            breadcrumb: 'wallet'
        }
    },
    {
        path: '/admin/wallets',
        component: PlatformWalletComponent,
        name: 'platform.wallets',
        meta: {
            isFrontend: false,
            auth: true,
            workspace: 'platform',
            breadcrumb: 'wallets'
        }
    }
]
