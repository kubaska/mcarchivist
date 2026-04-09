import {defineStore} from "pinia";
import {groupBy, intersection} from "lodash-es";
import api from "../api/api";

export const useConfigStore = defineStore('config', {
    state: () => ({
        platforms: [],
        requests: {},
        categories: [],
        gameVersions: [],
        loaders: [],
        rulesets: [],
        settings: {}
    }),
    getters: {
        getPlatform: state => id => state.platforms.find(platform => platform.id === id),
        getPlatformBySlug: state => slug => state.platforms.find(platform => platform.slug === slug),
        getRequestInfo: (state) => {
            return (platform, requestName) => {
                return state.requests[requestName]?.[platform];
            }
        },
        getLoadersForPlatform: (state) => {
            return (platform, projectTypes) => {
                return state.loaders.filter(loader =>
                    loader.remotes?.[platform] && intersection(loader.remotes[platform].project_types, projectTypes).length
                );
            }
        },
        getLoadersRemoteIdsForPlatform: (state) => {
            return (platform, projectTypes) => {
                return state.getLoadersForPlatform(platform, projectTypes).map(loader => {
                    return {
                        id: loader.id,
                        remote_id: loader.remotes[platform].remote_id,
                        name: loader.name
                    }
                });
            }
        },
        getLoadersForPlatformAnyProjectType: (state) => {
            return (platform) => state.loaders.filter(loader => loader.remotes?.[platform]);
        },
        gameVersionsRelease: (state) => {
            return state.gameVersions.filter(i => i.type === 0);
        },
        categoriesForProjectType: (state) => {
            return (platform, projectType) => {
                const categories = groupBy(state.categories.filter(
                    category => category.platform === platform && category.project_types?.includes(projectType)
                ), 'group');

                // Sort category groups
                return Object.keys(categories)
                    .sort()
                    .reduce((result, value) => {
                        result[value] = categories[value];
                        return result;
                    }, {});
            }
        },
        getSetting: (state) => {
            return (key, _default) => {
                if (state.settings[key] === undefined) {
                    console.error(`Settings key ${key} does not exist`);
                    return _default;
                }

                return state.settings[key];
            }
        },
        getSettingsStartingWith: (state) => {
            return (keyNames) => {
                // Wrap in array if it isn't one already
                const _keyNames = Array.isArray(keyNames) ? keyNames : [keyNames];
                return Object.fromEntries(Object.entries(state.settings).filter(([key, value]) => {
                    return _keyNames.some(keyName => key.startsWith(keyName));
                }));
            }
        },
    },
    actions: {
        async getConfig() {
            const r = await fetch('/api/config');
            const json = await r.json();

            this.platforms = json.platforms;
            this.requests = json.requests;
            this.categories = json.categories;
            this.gameVersions = json.game_versions;
            this.loaders = json.loaders;
            this.rulesets = json.rulesets;
            this.settings = json.settings;
        },

        saveSettings(settings) {
            return api.saveSettings(settings)
                .then(() => {
                    this.settings = { ...this.settings, ...settings };
                    return settings;
                });
        },

        createRuleset(name) {
            return api.createRuleset(name)
                .then(response => {
                    this.rulesets.push(response.data.data);
                    return response;
                });
        },
        updateRuleset(id, options) {
            return api.updateRuleset(id, options)
                .then(response => {
                    const index = this.rulesets.findIndex(ruleset => ruleset.id === id);
                    if (index >= 0) {
                        this.rulesets[index] = response.data.data;
                    } else {
                        console.error('Failed to update ruleset as it is missing')
                    }
                    return response;
                });
        },
        deleteRuleset(id) {
            return api.deleteRuleset(id)
                .then(() => {
                    this.rulesets = this.rulesets.filter(ruleset => ruleset.id !== id);
                });
        }
    }
});
