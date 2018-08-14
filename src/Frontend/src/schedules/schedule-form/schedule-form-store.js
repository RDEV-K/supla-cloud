import Vue from "vue";

export const mutations = {
    changeScheduleMode(state, newScheduleMode) {
        state.mode = newScheduleMode;
        state.nextRunDates = [];
        state.timeExpression = '';
    },
    updateTimeExpression(state, timeExpression) {
        state.timeExpression = timeExpression;
    },
    updateCaption(state, caption) {
        state.caption = caption;
    },
    updateRetry(state, retry) {
        state.retry = retry;
    },
    updateDateStart(state, date) {
        state.dateStart = date ? date.format() : moment().format();
    },
    updateDateEnd(state, date) {
        state.dateEnd = date ? date.format() : '';
    },
    fetchingNextRunDates(state) {
        state.fetchingNextRunDates = true;
    },
    updateNextRunDates(state, nextRunDates) {
        state.nextRunDates = nextRunDates;
        state.fetchingNextRunDates = false;
    },
    clearNextRunDates(state) {
        state.nextRunDates = [];
    },
    updateChannel(state, channelId) {
        state.channelId = channelId;
        state.actionId = undefined;
        state.actionParam = undefined;
    },
    updateAction(state, actionId) {
        state.actionId = actionId;
        state.actionParam = undefined;
    },
    updateActionParam(state, actionParam) {
        state.actionParam = actionParam;
    },
    submit(state) {
        state.submitting = true;
    },
    submitFailed(state) {
        state.submitting = false;
    },
    editSchedule(state, schedule) {
        state.schedule = schedule;
        state.mode = schedule.mode;
        state.timeExpression = schedule.timeExpression;
        state.dateStart = schedule.dateStart;
        state.dateEnd = schedule.dateEnd;
        state.channelId = schedule.channel.id;
        state.actionId = schedule.action.id;
        state.actionParam = schedule.actionParam;
        state.caption = schedule.caption;
        state.retry = schedule.retry;
    }
};

export const actions = {
    updateTimeExpression({commit, dispatch}, timeExpression) {
        commit('updateTimeExpression', timeExpression);
        dispatch('fetchNextRunDates');
    },

    updateDateStart({commit, dispatch}, date) {
        commit('updateDateStart', date);
        dispatch('fetchNextRunDates');
    },

    updateDateEnd({commit, dispatch}, date) {
        commit('updateDateEnd', date);
        dispatch('fetchNextRunDates');
    },

    fetchNextRunDates({commit, state, dispatch}) {
        if (!state.fetchingNextRunDates) {
            if (!state.timeExpression) {
                commit('clearNextRunDates');
            } else {
                commit('fetchingNextRunDates');
                let query = {
                    mode: state.mode,
                    timeExpression: state.timeExpression,
                    dateStart: state.dateStart,
                    dateEnd: state.dateEnd
                };
                Vue.http.post('schedules/next-run-dates', query)
                    .then(({body: nextRunDates}) => {
                        commit('updateNextRunDates', nextRunDates);
                        if (query.timeExpression != state.timeExpression || query.dateStart != state.dateStart || query.dateEnd != state.dateEnd) {
                            dispatch('fetchNextRunDates');
                        }
                    })
                    .catch(() => commit('updateNextRunDates', []));

            }
        }
    },

    submit({commit, state}, enableIfDisabled) {
        commit('submit');
        let promise;
        if (state.schedule.id) {
            promise = Vue.http.put(`schedules/${state.schedule.id}` + (enableIfDisabled ? '?enable=true' : ''), state);
        } else {
            promise = Vue.http.post('schedules', state);
        }
        return promise.catch(() => commit('submitFailed'));
    },

    loadScheduleToEdit({commit, dispatch}, schedule) {
        commit('editSchedule', schedule);
        dispatch('fetchNextRunDates');
    },
};
