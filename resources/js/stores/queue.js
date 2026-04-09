import {defineStore} from "pinia";
import api from "../api/api";
import {groupBy} from "lodash-es";
import {isJobFailed, isJobFinished} from "../utils/utils";

export const useQueueStore = defineStore('queue', {
    state: () => ({
        intervalId: null,
        pingInterval: 1000,
        pingsWithoutStateChange: 0,
        updateRunning: false,
        tasks: [],
        failedTasks: []
    }),
    getters: {
        getFailedTask: state => taskId => state.failedTasks.find(task => task.id === taskId),
        allTasks: state => [...state.tasks, ...state.failedTasks]
    },
    actions: {
        addJob(job) {
            this.tasks.push(job);
            this.setupInterval(1000);
        },
        async getQueue(firstFetch = false) {
            if (this.updateRunning) return;
            this.updateRunning = true;

            const res = await api.getQueue().catch(e => e);
            if (res instanceof Error) {
                this.updateRunning = false;
                return;
            }

            if (this.pingsWithoutStateChange >= 3) {
                const newInterval = Math.min(this.pingInterval * Math.floor(this.pingsWithoutStateChange / 3), 15000);
                if (this.pingInterval !== newInterval) {
                    this.setupInterval(newInterval);
                }
            }

            const groupedTasks = groupBy(res.data.data, task => isJobFinished(task.state) ? 'finished' : 'running');
            if (groupedTasks['finished']) {
                this.failedTasks = groupedTasks['finished'].filter(job => isJobFailed(job.state));
            } else {
                this.failedTasks = [];
            }

            if (groupedTasks['running']) {
                if (groupedTasks['running'].length === this.tasks.length) {
                    this.pingsWithoutStateChange += 1;
                } else {
                    this.pingsWithoutStateChange = 0;
                }

                this.tasks = groupedTasks['running'];
            } else {
                this.tasks = [];
                this.pingsWithoutStateChange = 0;
            }

            if (firstFetch && this.tasks.length) {
                this.setupInterval();
            }

            if (this.tasks.length === 0) {
                this.clearInterval();
                this.pingInterval = 1000;
                this.pingsWithoutStateChange = 0;
            }

            this.updateRunning = false;
        },
        setupInterval(interval = 3000) {
            this.clearInterval();
            this.pingInterval = interval;
            this.intervalId = setInterval(this.getQueue, interval);
        },
        clearInterval() {
            if (this.intervalId) clearInterval(this.intervalId);
        },
        cancelJob(jobId) {
            return api.cancelQueueJob(jobId)
                .then(response => {
                    this.tasks = this.tasks.filter(task => task.id !== jobId);
                    this.failedTasks = this.failedTasks.filter(task => task.id !== jobId);
                    return true;
                })
                .catch(err => {
                    return false;
                });
        },
        retryJob(jobId) {
            return api.retryQueueJob(jobId)
                .then(response => {
                    this.failedTasks = this.failedTasks.filter(job => job.id !== jobId);
                    // task automatically added by middleware
                })
                .catch(e => e);
        }
    }
});
