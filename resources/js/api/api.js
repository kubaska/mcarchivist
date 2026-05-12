import request from "./request";

export default {
    getGameVersions(options = {}) {
        return request.get('/game-versions', { params: options });
    },
    updateGameVersionIndex(options = {}) {
        return request.post('/game-versions/update-index', options);
    },
    getGameVersionFiles(id) {
        return request.get(`/game-versions/${id}/files`);
    },
    archiveGameVersion(id, options = {}) {
        return request.post(`/game-versions/${id}/archive`, options);
    },
    revalidateGameVersion(id, options = {}) {
        return request.post(`/game-versions/${id}/revalidate`, options);
    },
    deleteGameVersion(id) {
        return request.delete(`/game-versions/${id}`);
    },
    deleteGameVersionFile(versionId, fileId) {
        return request.delete(`/game-versions/${versionId}/files/${fileId}`);
    },

    getLoaderVersions(id, options) {
        return request.get(`/loaders/${id}`, { params: options });
    },
    getLoaderFiles(id, versionId) {
        return request.get(`/loaders/${id}/versions/${versionId}/files`);
    },
    updateLoaderIndex(id, options = {}) {
        return request.post(`/loaders/${id}/update-index`, options);
    },
    archiveLoaderVersion(id, versionId, options = {}) {
        return request.post(`/loaders/${id}/archive`, { version_id: versionId, ...options });
    },
    revalidateLoaderVersion(id, versionId, options = {}) {
        return request.post(`/loaders/${id}/versions/${versionId}/revalidate`, options);
    },
    deleteLoaderVersion(id, versionId) {
        return request.delete(`/loaders/${id}/versions/${versionId}`);
    },
    deleteLoaderFile(id, versionId, fileId) {
        return request.delete(`/loaders/${id}/versions/${versionId}/files/${fileId}`);
    },

    searchProjects(options) {
        return request.get('/projects', { params: options });
    },
    getProject(id, options) {
        return request.get(`/projects/${id}`, { params: options });
    },
    archiveProject(id, rules) {
        return request.post(`/projects/${id}/archive`, rules);
    },
    getProjectAuthors(id, options) {
        return request.get(`/projects/${id}/authors`, { params: options });
    },
    getProjectVersions(id, options) {
        return request.get(`/projects/${id}/versions`, { params: options });
    },
    getProjectDependencies(id, options) {
        return request.get(`/projects/${id}/dependencies`, { params: options });
    },
    getProjectDependants(id, options) {
        return request.get(`/projects/${id}/dependants`, { params: options });
    },
    getVersionFiles(id, versionId, options) {
        return request.get(`/projects/${id}/versions/${versionId}/files`, { params: options });
    },
    deleteVersion(id, versionId, options = {}) {
        return request.delete(`/projects/${id}/versions/${versionId}`, { params: options });
    },
    deleteVersionFile(id, versionId, fileId, options = {}) {
        return request.delete(`/projects/${id}/versions/${versionId}/files/${fileId}`, { params: options });
    },
    archiveProjectVersion(id, versionId, options = {}) {
        return request.post(`/projects/${id}/versions/${versionId}/archive`, options);
    },
    revalidateVersion(id, versionId, options) {
        return request.post(`/projects/${id}/versions/${versionId}/revalidate`, options);
    },
    getVersionDependencies(id, versionId, options) {
        return request.get(`/projects/${id}/versions/${versionId}/dependencies`, { params: options });
    },
    getVersionDependants(id, versionId, options) {
        return request.get(`/projects/${id}/versions/${versionId}/dependants`, { params: options });
    },
    getRelatedProjects(id, options = {}) {
        return request.post(`/projects/${id}/related`, options);
    },
    mergeProjects(id, options) {
        return request.post(`/projects/${id}/merge`, options);
    },
    unmergeProject(projectId) {
        return request.post(`/projects/${projectId}/unmerge`);
    },
    setDefaultProject(projectId) {
        return request.post(`/projects/${projectId}/default`);
    },

    getQueue(ids = []) {
        return request.get('/queue', { params: { ids } });
    },
    cancelQueueJob(jobId) {
        return request.post(`/queue/${jobId}/cancel`);
    },
    retryQueueJob(jobId) {
        return request.post(`/queue/${jobId}/retry`);
    },

    getSettings() {
        return request.get('/settings');
    },
    saveSettings(settings) {
        return request.post('/settings', settings);
    },
    getDirectories(options) {
        return request.get('/directory-selector', { params: options });
    },

    createRuleset(name) {
        return request.post('/rulesets', { name });
    },
    updateRuleset(id, options) {
        return request.post('/rulesets/'+id, options);
    },
    deleteRuleset(id) {
        return request.delete(`/rulesets/${id}`);
    }
}
