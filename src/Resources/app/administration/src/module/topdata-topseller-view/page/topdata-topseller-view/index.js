import template from './topdata-topseller-view.html.twig';

const { Component, Mixin } = Shopware;

Component.register('topdata-topseller-view', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            viewMode: 'week',
            currentDate: new Date(),
            items: [],
            total: 0,
            isLoading: false,
            startDate: null,
            endDate: null,
        };
    },

    computed: {
        periodLabel() {
            const y = this.currentDate.getFullYear();
            const m = this.currentDate.getMonth();
            const d = this.currentDate.getDate();

            if (this.viewMode === 'week') {
                const week = this.getWeekNumber(this.currentDate);
                return `KW ${week}, ${y}`;
            }

            const monthNames = [
                'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
            ];
            return `${monthNames[m]} ${y}`;
        },
    },

    created() {
        this.updateDateRange();
        this.fetchTopsellers();
    },

    methods: {
        getWeekNumber(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNum = d.getUTCDay() || 7;
            d.setUTCDate(d.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        },

        getMonday(date) {
            const d = new Date(date);
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            d.setDate(diff);
            d.setHours(0, 0, 0, 0);
            return d;
        },

        updateDateRange() {
            const y = this.currentDate.getFullYear();
            const m = this.currentDate.getMonth();
            const d = this.currentDate.getDate();

            if (this.viewMode === 'week') {
                const monday = this.getMonday(this.currentDate);
                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);
                this.startDate = this.formatDate(monday);
                this.endDate = this.formatDate(sunday);
            } else {
                const first = new Date(y, m, 1);
                const last = new Date(y, m + 1, 0);
                this.startDate = this.formatDate(first);
                this.endDate = this.formatDate(last);
            }
        },

        formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        navigate(direction) {
            const y = this.currentDate.getFullYear();
            const m = this.currentDate.getMonth();
            const d = this.currentDate.getDate();

            if (this.viewMode === 'week') {
                const current = new Date(y, m, d);
                current.setDate(current.getDate() + (direction * 7));
                this.currentDate = current;
            } else {
                this.currentDate = new Date(y, m + direction, 1);
            }

            this.updateDateRange();
            this.fetchTopsellers();
        },

        switchMode(mode) {
            this.viewMode = mode;
            this.currentDate = new Date();
            this.updateDateRange();
            this.fetchTopsellers();
        },

        fetchTopsellers() {
            this.isLoading = true;

            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const language = Shopware.Store.get('session').currentLocale || 'de-DE';

            httpClient
                .get('topdata/topseller-export/topsellers', {
                    params: {
                        startDate: this.startDate,
                        endDate: this.endDate,
                        languageCode: language,
                    },
                })
                .then((response) => {
                    this.items = response.data.data || [];
                    this.total = response.data.total || 0;
                    this.isLoading = false;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('topdata-topseller-view.messages.error'),
                    });
                    this.isLoading = false;
                });
        },

        switchDate(dateStr) {
            this.currentDate = new Date(dateStr + 'T00:00:00');
            this.updateDateRange();
            this.fetchTopsellers();
        },
    },
});
