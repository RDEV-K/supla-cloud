import {openDB} from "idb/with-async-ittr";
import {DateTime} from "luxon";
import {CHART_TYPES, fillGaps} from "@/channels/history/channel-measurements-history-chart-strategies";

export class IndexedDbMeasurementLogsStorage {
    constructor(channel) {
        this.channel = channel;
        this.chartStrategy = CHART_TYPES[this.channel.function.name];
        this.db = openDB(`channel_measurement_logs_${this.channel.id}`, 1, {
            upgrade(db) {
                if (!db.objectStoreNames.contains("logs")) {
                    const os = db.createObjectStore("logs", {keyPath: 'date_timestamp'});
                    os.createIndex("date", "date", {unique: true});
                }
            },
        });
    }

    adjustLogBeforeStorage(log) {
        log.date_timestamp = +log.date_timestamp;
        log.date = DateTime.fromSeconds(log.date_timestamp).toJSDate();
        return this.chartStrategy.fixLog(log);
    }

    async fetchSparseLogs() {
        const oldestLog = await this.getOldestLog();
        if (!oldestLog) {
            return [];
        }
        const newestLog = await this.getNewestLog();
        const availableStrategies = this.getAvailableAggregationStrategies(newestLog.date_timestamp - oldestLog.date_timestamp);
        const sparseLogs = await this.fetchDenseLogs(0, newestLog.date_timestamp + 1, availableStrategies[availableStrategies.length - 1]);
        return sparseLogs;
    }

    getAvailableAggregationStrategies(timestampRange) {
        const strategies = [];
        if (timestampRange < 86400 * 7) {
            strategies.push('all');
        }
        if (timestampRange > 3600 * 6 && timestampRange < 86400 * 7) {
            strategies.push('hour');
        }
        if (timestampRange > 86400 * 2 && timestampRange < 86400 * 365) {
            strategies.push('day');
        }
        if (timestampRange > 86400 * 60) {
            strategies.push('month');
        }
        return strategies;
    }

    async getNewestLog() {
        const index = (await this.db).transaction('logs').store.index('date');
        const cursor = await index.openCursor(null, 'prev');
        return cursor?.value;
    }

    async getOldestLog() {
        const index = (await this.db).transaction('logs').store.index('date');
        const cursor = await index.openCursor(null);
        return cursor?.value;
    }

    async fetchDenseLogs(afterTimestamp, beforeTimestamp, aggregationMethod) {
        const fromDate = DateTime.fromSeconds(afterTimestamp).toJSDate();
        const toDate = DateTime.fromSeconds(beforeTimestamp).toJSDate();
        const range = IDBKeyRange.bound(fromDate, toDate);
        const logs = await (await this.db).getAllFromIndex('logs', 'date', range);
        const keyFunc = {
            hour: (log) => `${log.date.getFullYear()}_${log.date.getMonth()}_${log.date.getDate()}_${log.date.getHours()}`,
            day: (log) => `${log.date.getFullYear()}_${log.date.getMonth()}_${log.date.getDate()}`,
            month: (log) => `${log.date.getFullYear()}_${log.date.getMonth()}`,
        }[aggregationMethod];
        if (keyFunc) {
            const aggregatedLogsKeys = {};
            const aggregatedLogs = [];
            console.time('aggregating');
            logs.forEach(log => {
                const key = keyFunc(log);
                if (aggregatedLogsKeys[key] === undefined) {
                    aggregatedLogsKeys[key] = aggregatedLogs.length;
                    aggregatedLogs.push([]);
                }
                aggregatedLogs[aggregatedLogsKeys[key]].push(log);
            });
            console.timeEnd('aggregating');
            const finalLogs = aggregatedLogs.map(this.chartStrategy.aggregateLogs);
            return finalLogs;
        } else {
            return logs;
        }
    }

    async init(vue) {
        const lastLog = await this.getNewestLog();
        const afterTimestamp = (+lastLog?.date_timestamp || 0) + 1;
        return vue.$http.get(`channels/${this.channel.id}/measurement-logs?order=ASC&afterTimestamp=${afterTimestamp}`)
            .then(async ({body: logItems}) => {
                if (logItems.length) {
                    const lastLog = await this.getNewestLog();
                    if (lastLog) {
                        logItems.unshift(lastLog);
                    }
                    logItems = fillGaps(logItems, 600, this.chartStrategy.emptyLog());
                    logItems = this.chartStrategy.interpolateGaps(logItems)
                    if (lastLog) {
                        logItems.shift();
                    }
                    const tx = (await this.db).transaction('logs', 'readwrite');
                    logItems.forEach(async (log) => {
                        log = this.adjustLogBeforeStorage(log);
                        await tx.store.add(log);
                    });
                    await tx.done;
                    return this.init(vue);
                }
            });
    }
}
