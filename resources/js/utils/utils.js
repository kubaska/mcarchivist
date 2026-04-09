export const capitalizeString = (str) => str.charAt(0).toUpperCase() + str.slice(1);

/**
 * Check if the provided element, or any parent element, passes test provided by the callback.
 *
 * @param element
 * @param callback
 * @returns {boolean}
 */
export const isDescendantOf = (element, callback) => {
    if (element === null || element.tagName.toLowerCase() === 'body') return false;
    if (callback(element)) return true;
    return isDescendantOf(element.parentElement, callback);
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

// Determines which game version types given mod loader have releases for.
// e.g. Forge, NeoForge, Fabric Intr have releases for stable and snapshot Minecraft versions.
export const getLoaderArchivableGameVersionTypes = (loader) => {
    switch (loader) {
        case 'Forge':
        case 'NeoForge':
        case 'Fabric Intermediary':
            return [{ id: 'release', name: 'Release' }, { id: 'snapshot', name: 'Snapshot' }];
        default:
            return [];
    }
}

export const getLoaderArchiveFilter = (loader) => {
    switch (loader) {
        case 'Forge':
            return [{ id: '*', name: 'All' }, { id: 'highlighted', name: 'Highlighted' }];
        case 'NeoForge':
        case 'Fabric':
            return [{ id: '*', name: 'All' }, { id: 'latest', name: 'Latest' }];
        default:
            return [];
    }
}

const JOB_TYPES = {
    0: { id: 0, name: 'Archiving' },
    1: { id: 1, name: 'Revalidating' },
    2: { id: 2, name: 'Updating Index' }
};

export const getJobTypeName = (type) => JOB_TYPES[type]?.name;

export const isJobFinished = (jobState) => [2,3,4].includes(jobState);
export const isJobFailed = (jobState) => jobState === 3;

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

export const GAME_VERSION_COMPONENTS = [
    { id: 'client', name: 'Client' },
    { id: 'server', name: 'Server' },
    { id: 'windows_server', name: 'Server (Windows)', hint: 'Available only for old betas, provided as a self contained EXE file.' },
    { id: 'client_mappings', name: 'Mappings (client)' },
    { id: 'server_mappings', name: 'Mappings (server)' },
];

export const formatComponentName = (name) => {
    switch (name) {
        case 'windows_server': return 'Server (Windows)';
        case 'client_mappings': return 'Client mappings';
        case 'server_mappings': return 'Server mappings';
        default: return capitalizeString(name);
    }
};

export const getIconNameForGameVersionComponent = (name) => {
    switch (name) {
        case 'client': return 'display';
        case 'server': return 'server';
        case 'windows_server': return 'server';
        case 'client_mappings': return 'code';
        case 'server_mappings': return 'code';
        default: return 'file';
    }
}

export const getLoaderComponents = (name) => {
    switch (name) {
        case 'forge':
            return [
                { id: 'client', name: 'Client' },
                { id: 'server', name: 'Server' },
                { id: 'universal', name: 'Universal' },
                { id: 'installer', name: 'Installer' },
                { id: 'changelog', name: 'Changelog' },
                { id: 'sources', name: 'Sources', hint: '"src" in older Forge versions' },
                { id: 'mdk', name: 'mdk (Development Kit)' },
                { id: 'userdev', name: 'Userdev' },
                { id: 'launcher', name: 'Launcher' },
            ];
        case 'neoforge':
            return [
                { id: 'universal', name: 'Universal' },
                { id: 'installer', name: 'Installer' },
                { id: 'changelog', name: 'Changelog' },
                { id: 'sources', name: 'Sources' },
                { id: 'userdev', name: 'Userdev' },
            ];
        default:
            return [];
    }
}

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
