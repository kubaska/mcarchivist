import {defineStore} from "pinia";
import api from "../api/api";
import {groupBy} from "lodash-es";
import {isJobFinished} from "../utils/utils";
import {useConfigStore} from "./config";

export const useQueueStore = defineStore('queue', {
    state: () => ({
        intervalId: null,
        pingInterval: 1000,
        pingsWithoutStateChange: 0,
        updateRunning: false,
        taskIds: [],
        tasks: [],
        previousTasks: [],
        previousTasksCursor: null,
        previousTasksLimited: true,
        previousTasksExhausted: false
    }),
    getters: {
        getPreviousTask: state => taskId => state.previousTasks.find(task => task.id === taskId),
        allTasks: state => [...state.tasks, ...state.previousTasks]
    },
    actions: {
        addJob(job) {
            this.tasks.push(job);
            this.taskIds.push(job.id);
            this.setupInterval(1000);
        },
        async getQueue(firstFetch = false) {
            if (this.updateRunning) return;
            this.updateRunning = true;

            const res = await api.getQueue(firstFetch ? [] : this.taskIds).catch(e => e);
            if (res instanceof Error) {
                this.updateRunning = false;
                return;
            }

            if (this.pingsWithoutStateChange >= 5) {
                const newInterval = Math.min(this.pingInterval * Math.floor(this.pingsWithoutStateChange / 3), 15000);
                if (this.pingInterval !== newInterval) {
                    this.setupInterval(newInterval);
                    this.pingsWithoutStateChange = 0;
                }
            }

            const groupedTasks = groupBy(res.data.data, task => isJobFinished(task.state) ? 'finished' : 'running');
            if (groupedTasks['finished']) {
                if (groupedTasks['finished'].find(job => job.job_type === 3)) {
                    useConfigStore().fetchSettings();
                }

                this.previousTasks.unshift(...groupedTasks['finished'].filter(task => ! this.previousTasks.map(pt => pt.id).includes(task.id)));
                if (this.previousTasksLimited && this.previousTasks.length > 20) {
                    this.previousTasks = this.previousTasks.slice(0, 20);
                }

                this.taskIds = this.taskIds.filter(taskId => ! groupedTasks['finished'].some(task => task.id === taskId));
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
                this.taskIds = this.tasks.map(task => task.id);
                this.setupInterval();
            }

            if (this.tasks.length === 0) {
                this.clearInterval();
                this.pingInterval = 1000;
                this.pingsWithoutStateChange = 0;
            }

            this.updateRunning = false;
        },
        async getPreviousTasks() {
            this.previousTasksLimited = false;
            const tasks = await api.getPreviousTasks(
                this.previousTasksCursor
                    ? { cursor: this.previousTasksCursor }
                    : { exclude: this.previousTasks.map(t => t.id) }
            );

            if (tasks.data.data.length === 20) {
                this.previousTasks.push(...tasks.data.data.filter(task => ! this.previousTasks.map(pt => pt.id).includes(task.id)));
                this.previousTasksCursor = tasks.data.data[tasks.data.data.length - 1].id;
            } else {
                this.previousTasksExhausted = true;
            }
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
                    this.previousTasks = this.previousTasks.filter(task => task.id !== jobId);

                    this.previousTasks.unshift(response.data.data);

                    return true;
                });
        },
        retryJob(jobId) {
            return api.retryQueueJob(jobId)
                .then(response => {
                    this.previousTasks = this.previousTasks.filter(job => job.id !== jobId);
                    this.addJob(response.data.data);
                });
        }
    }
});
