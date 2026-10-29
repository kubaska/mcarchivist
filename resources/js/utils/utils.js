export const capitalizeString = (str) => str.charAt(0).toUpperCase() + str.slice(1);

export const toBool = (str) => String(str).trim().toLowerCase() === 'true';

/**
 * Check if the provided element, or any parent element, is a descendant of the target,
 * or passes test provided by the callback.
 *
 * @param element
 * @param targetOrCallback
 * @returns {boolean}
 */
export const isDescendantOf = (element, targetOrCallback) => {
    if (element === null || element.tagName.toLowerCase() === 'body') return false;

    if (targetOrCallback instanceof Function) {
        if (targetOrCallback(element)) return true;
    } else {
        if (element === targetOrCallback) return true;
    }

    return isDescendantOf(element.parentElement, targetOrCallback);
}

export const downloadFile = (url) => {
    const el = document.createElement('a');
    el.href = url;
    el.className = 'd-none';
    el.target = '_blank';
    document.body.appendChild(el);
    el.click();
    setTimeout(() => el.remove(), 250);
}

export const downloadFiles = (urls) => {
    let timeout = 500;

    urls.forEach(url => {
        setTimeout(() => downloadFile(url), timeout);
        timeout += 500;
    });
}

export const getLocalSortingOptions = () => {
    return [
        { id: 'downloads', name: 'Total downloads' },
        { id: 'name', name: 'Name' },
        { id: 'latest', name: 'Latest' },
        { id: 'oldest', name: 'Oldest' },
    ];
}

export const VERSION_TYPES = {
    0: { id: 0, symbol: 'R', name: 'Release',  bs_bg_class: 'bg-success' },
    1: { id: 1, symbol: 'B', name: 'Beta',     bs_bg_class: 'bg-info' },
    2: { id: 2, symbol: 'A', name: 'Alpha',    bs_bg_class: 'bg-warning' },
    3: { id: 3, symbol: 'S', name: 'Snapshot', bs_bg_class: 'bg-danger' },
    4: { id: 4, symbol: 'R*', name: 'Release (Highlighted)', bs_bg_class: 'bg-success' },
};

// Only release, beta, alpha
export const getBaseVersionTypes = () => {
    return Object.values(VERSION_TYPES).slice(0, 3);
};

// Only release, beta, alpha, snapshot
export const getMojangVersionTypes = () => {
    return Object.values(VERSION_TYPES).slice(0, 4);
}

export const getLoaderReleaseTypes = (loader) => {
    switch (loader) {
        case 'Forge':
            // Rel *, Rel
            return [VERSION_TYPES[4], VERSION_TYPES[0]];
        case 'NeoForge':
            // Release, Beta
            return [VERSION_TYPES[0], VERSION_TYPES[1]];
        case 'Fabric':
        case 'Fabric Intermediary':
            // Release
            return [VERSION_TYPES[0]];
        default:
            return getBaseVersionTypes();
    }
};

export const JOB_TYPE = {
    ARCHIVING: 0,
    REVALIDATING: 1,
    UPDATING_INDEX: 2,
    EXCLUSIVE: 3
};
const JOB_TYPES = {
    [JOB_TYPE.ARCHIVING]: { id: JOB_TYPE.ARCHIVING, name: 'Archiving' },
    [JOB_TYPE.REVALIDATING]: { id: JOB_TYPE.REVALIDATING, name: 'Revalidating' },
    [JOB_TYPE.UPDATING_INDEX]: { id: JOB_TYPE.UPDATING_INDEX, name: 'Updating Index' },
    [JOB_TYPE.EXCLUSIVE]: { id: JOB_TYPE.EXCLUSIVE, name: 'Exclusive' }
};
export const JOB_STATE = {
    QUEUED: 0,
    RUNNING: 1,
    FINISHED: 2,
    FAILED: 3,
    CANCELLED: 4,
    FINISHED_WITH_WARNINGS: 5,
    FINISHED_WITH_ERRORS: 6,
};
const JOB_STATE_DETAILS = {
    [JOB_STATE.QUEUED]: { id: JOB_STATE.QUEUED, name: 'Queued' },
    [JOB_STATE.RUNNING]: { id: JOB_STATE.RUNNING, name: 'Running' },
    [JOB_STATE.FINISHED]: { id: JOB_STATE.FINISHED, name: 'Finished' },
    [JOB_STATE.FAILED]: { id: JOB_STATE.FAILED, name: 'Failed' },
    [JOB_STATE.CANCELLED]: { id: JOB_STATE.CANCELLED, name: 'Cancelled' },
    [JOB_STATE.FINISHED_WITH_WARNINGS]: { id: JOB_STATE.FINISHED_WITH_WARNINGS, name: 'Finished with warnings' },
    [JOB_STATE.FINISHED_WITH_ERRORS]: { id: JOB_STATE.FINISHED_WITH_ERRORS, name: 'Finished with errors' },
};

export const getJobTypeName = (type) => JOB_TYPES[type]?.name;
export const getJobState = (jobState) => JOB_STATE_DETAILS[jobState];

export const isJobFinished = (jobState) => [
    JOB_STATE.FINISHED, JOB_STATE.FAILED, JOB_STATE.CANCELLED, JOB_STATE.FINISHED_WITH_ERRORS, JOB_STATE.FINISHED_WITH_WARNINGS
].includes(jobState);

export const getProjectTypes = () => [
    { id: 0, name: 'Mod', name_plural: 'Mods' },
    { id: 1, name: 'Modpack', name_plural: 'Modpacks' },
    { id: 2, name: 'Plugin', name_plural: 'Plugins' },
    { id: 3, name: 'Resource Pack', name_plural: 'Resource Packs' },
    { id: 4, name: 'Datapack', name_plural: 'Datapacks' },
    { id: 5, name: 'World', name_plural: 'Worlds' },
    { id: 6, name: 'Shader', name_plural: 'Shaders' },
    { id: 7, name: 'Addon', name_plural: 'Addons' },
    { id: 8, name: 'Customization', name_plural: 'Customization' },
];

export const formatComponentName = (name) => {
    switch (name) {
        case 'windows_server': return 'Server (Windows)';
        case 'client_mappings': return 'Client mappings';
        case 'server_mappings': return 'Server mappings';
        default: return capitalizeString(name);
    }
};

// https://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
export const formatBytes = (bytes, decimals = 2) => {
    if (!bytes) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KiB', 'MiB', 'GiB'];

    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
}

/**
 * Filter project types by supplied IDs array.
 *
 * @param {array} ids
 */
export const getProjectTypesById = (ids) => getProjectTypes().filter(type => ids.includes(type.id));

export const getDefaultSearchRequestInfo = () => {
    return {
        project_type: { options: getProjectTypes().map(type => type.id) },
        query: { max: 200 },
        game_versions: {},
        loaders: {},
        categories: {},
        sort_by: {},
        page: {}
    };
}
