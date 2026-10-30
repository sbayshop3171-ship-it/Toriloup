<template>
    <LoadingComponent :props="loading" />

    <section class="db-card">
        <header class="db-card-header">
            <div>
                <h2 class="db-card-title">{{ $t('menu.notification_preferences') }}</h2>
                <p class="mt-1 text-sm text-paragraph">
                    {{ $t('label.notification_preferences_intro') }}
                </p>
            </div>
        </header>

        <div class="db-card-body">
            <p class="mb-5 rounded-lg bg-primary/5 px-4 py-3 text-sm text-paragraph">
                {{ $t('label.notification_preferences_scope') }}
            </p>

            <div v-if="events.length" class="grid gap-3 lg:grid-cols-2">
                <article v-for="event in events" :key="event.event_key"
                    class="flex items-start justify-between gap-4 rounded-xl border border-[#EFF0F6] bg-white p-4">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-heading">{{ event.label }}</h3>
                        <p class="mt-1 text-xs text-paragraph">{{ event.description }}</p>
                        <p v-if="!event.owner_enabled" class="mt-2 text-xs font-medium text-amber-700">
                            {{ $t('label.owner_notification_paused') }}
                        </p>
                    </div>
                    <div class="custom-switch flex-shrink-0">
                        <input :id="'preference-' + event.event_key" type="checkbox" :checked="event.enabled" :disabled="saving === event.event_key"
                            @change="savePreference(event, $event)">
                        <label :for="'preference-' + event.event_key">{{ event.enabled ? $t('label.on') : $t('label.off') }}</label>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>

<script>
import axios from 'axios';
import alertService from '../../../../services/alertService';
import LoadingComponent from '../../components/LoadingComponent';

export default {
    name: 'NotificationPreferencesComponent',
    components: { LoadingComponent },
    data() {
        return {
            loading: { isActive: false },
            saving: null,
            events: [],
        };
    },
    mounted() {
        this.loadPreferences();
    },
    methods: {
        loadPreferences() {
            this.loading.isActive = true;
            axios.get('/admin/notification-preferences')
                .then((response) => { this.events = response.data.data || []; })
                .catch((error) => { alertService.error(error); })
                .finally(() => { this.loading.isActive = false; });
        },
        savePreference(event, inputEvent) {
            const enabled = inputEvent.target.checked;
            const previous = event.enabled;
            event.enabled = enabled;
            this.saving = event.event_key;

            axios.patch('/admin/notification-preferences/' + encodeURIComponent(event.event_key), { enabled })
                .then((response) => { Object.assign(event, response.data.data || {}); })
                .catch((error) => {
                    event.enabled = previous;
                    inputEvent.target.checked = previous;
                    alertService.error(error);
                })
                .finally(() => { this.saving = null; });
        },
    },
};
</script>
