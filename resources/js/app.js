import Vue from 'vue';
import Base from './base';
import axios from 'axios';
import Routes from './routes';
import VueRouter from 'vue-router';
import VueJsonPretty from 'vue-json-pretty';
import moment from 'moment-timezone';

require('bootstrap');

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

Vue.use(VueRouter);

window.Popper = require('popper.js').default;

moment.tz.setDefault(Reporting .timezone);

window.Reporting .basePath = '/' + window.Reporting .path;

let routerBasePath = window.Reporting .basePath + '/';

if (window.Reporting .path === '' || window.Reporting .path === '/') {
    routerBasePath = '/';
    window.Reporting .basePath = '';
}

const router = new VueRouter({
    routes: Routes,
    mode: 'history',
    base: routerBasePath,
});

Vue.component('vue-json-pretty', VueJsonPretty);
Vue.component('related-entries', require('./components/RelatedEntries.vue').default);
Vue.component('index-screen', require('./components/IndexScreen.vue').default);
Vue.component('preview-screen', require('./components/PreviewScreen.vue').default);
Vue.component('alert', require('./components/Alert.vue').default);

Vue.mixin(Base);

new Vue({
    el: '#nitm-reporting',

    router,

    data() {
        return {
            alert: {
                type: null,
                autoClose: 0,
                message: '',
                confirmationProceed: null,
                confirmationCancel: null,
            },

            autoLoadsNewEntries: localStorage.autoLoadsNewEntries === '1',

            recording: Reporting .recording,
        };
    },

    methods: {
        autoLoadNewEntries() {
            if (!this.autoLoadsNewEntries) {
                this.autoLoadsNewEntries = true;
                localStorage.autoLoadsNewEntries = 1;
            } else {
                this.autoLoadsNewEntries = false;
                localStorage.autoLoadsNewEntries = 0;
            }
        },

        toggleRecording() {
            axios.post(Reporting .basePath + '/nitm-reporting-api/toggle-recording');

            window.Reporting .recording = !Reporting .recording;
            this.recording = !this.recording;
        },
    },
});
