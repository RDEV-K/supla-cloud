import Vue from 'vue'
import Vuex from 'vuex'
import ScheduleForm from './schedule-form.vue'
import * as mutations from './mutations'
import * as actions from './actions'

new Vue({
    el: '#schedule-form',
    store: new Vuex.Store({
        state: {
            scheduleMode: 'once',
            timeExpression: '',
            fetchingNextRunDates: false,
            nextRunDates: [],
        },
        mutations: mutations,
        actions: actions,
        strict: process.env.NODE_ENV !== 'production'
    }),
    render: h => h(ScheduleForm)
})
