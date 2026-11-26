function openFilesDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('BabylonFiles', 1);

        request.onupgradeneeded = () => {
            request.result.createObjectStore('handles');
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function getFileHandle(key) {
    const db = await openFilesDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction('handles', 'readonly');
        const request = tx.objectStore('handles').get(key);

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
        tx.oncomplete = () => db.close();
    });
}

async function storeFileHandle(key, handle) {
    const db = await openFilesDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction('handles', 'readwrite');

        tx.objectStore('handles').put(handle, key);

        tx.oncomplete = () => {
            db.close();
            resolve();
        };

        tx.onerror = () => {
            db.close();
            reject(tx.error);
        };

        tx.onabort = () => {
            db.close();
            reject(tx.error);
        };
    });
}

function quickSaveKey(format, lang) {
    return JSON.stringify([
        new URL('.', location.href).pathname,
        config.project_name || 'Untitled',
        format,
        lang
    ]);
}

async function saveCurrentLanguage(changeLocation = false) {

    const format = config.quick_save_format;
    const lang = activeLang;

    if (!format || !lang) return;

    const btn = document.getElementById('quickSave');
    const status = document.getElementById('quickSaveStatus');

    if (btn.disabled) return;

    const allBtn = document.getElementById('quickSaveAll');

    const key = quickSaveKey(format, lang);

    btn.disabled = true;

    if (allBtn) allBtn.disabled = true;

    status.style.display = 'block';
    status.textContent = 'Saving…';

    try {
        let handle = changeLocation
            ? null
            : await getFileHandle(key);

        // First save, or choosing a different location.
        if (!handle) {

            const extensions = {
                csvsingle: 'csv',
                csv: 'csv',
                androidxml: 'xml',
                po: 'po'
            };

            const ext = extensions[format];

            handle = await window.showSaveFilePicker({
                suggestedName: ext ? `${lang}.${ext}` : lang
            });
        }

        // Check whether we still have write permission.
        const options = { mode: 'readwrite' };

        if (await handle.queryPermission(options) !== 'granted') {

            if (await handle.requestPermission(options) !== 'granted') {
                throw new Error('Write permission denied');
            }
        }

        await writeLanguageFile(handle, format, lang);

        // Remember the destination after successful saving.
        await storeFileHandle(key, handle);

        status.textContent = `Saved: ${handle.name}`;

    } catch (err) {

        if (err.name === 'AbortError') {
            status.textContent = '';
        } else {
            status.textContent = 'Save failed';
            alert(err.message);
            console.error(err);
        }

    } finally {
        status.style.display = '';
        btn.disabled = false;
        if (allBtn) {
            allBtn.disabled = !window.showDirectoryPicker;
        }
    }
}

function initQuickSave() {

    const btn = document.getElementById('quickSave');
    const tool = document.getElementById('quickSaveTool');

    if (!btn || !config.quick_save_format) return;

    tool.hidden = false;

    if (!window.showSaveFilePicker) {
        btn.disabled = true;
        btn.title = 'Direct file saving is not supported by this browser';
        return;
    }

    btn.addEventListener('click', () => {
        saveCurrentLanguage();
    });

    btn.addEventListener('contextmenu', e => {
        e.preventDefault();
        saveCurrentLanguage(true);
    });

    const allBtn = document.getElementById('quickSaveAll');

    if (allBtn) {

        if (!window.showDirectoryPicker) {
            allBtn.disabled = true;
            allBtn.title = 'Directory saving is not supported';
        } else {

            allBtn.addEventListener('click', () => {
                saveAllLanguages();
            });

            allBtn.addEventListener('contextmenu', e => {
                e.preventDefault();
                saveAllLanguages(true);
            });
        }
    }
}

async function writeLanguageFile(handle, format, lang) {

    const params = new URLSearchParams({
        action: 'export',
        format: format,
        lang: lang
    });

    const response = await fetch(
        `api.php?${params}`,
        { cache: 'no-store' }
    );

    if (!response.ok) {
        throw new Error(`Export failed: ${lang}`);
    }

    const contentType =
        response.headers.get('Content-Type') || '';

    if (
        contentType.includes('application/json') ||
        contentType.includes('text/html')
    ) {
        throw new Error(`Unexpected export response: ${lang}`);
    }

    const content = await response.blob();
    const writable = await handle.createWritable();

    try {
        await writable.write(content);
        await writable.close();
    } catch (err) {
        try {
            await writable.abort();
        } catch (_) {}
        throw err;
    }
}

async function saveAllLanguages(changeLocation = false) {

    const format = config.quick_save_format;

    if (!format) return;

    const btn = document.getElementById('quickSaveAll');
    const singleBtn = document.getElementById('quickSave');
    const status = document.getElementById('quickSaveStatus');

    if (btn.disabled || singleBtn.disabled) return;

    const key = quickSaveKey(format, '__directory__');

    btn.disabled = true;
    singleBtn.disabled = true;

    status.textContent = 'Preparing…';

    let saved = 0;
    let failed = [];

    try {

        let directory = changeLocation
            ? null
            : await getFileHandle(key);

        // Choose destination directory.
        if (!directory) {
            directory = await window.showDirectoryPicker({
                mode: 'readwrite'
            });
        }

        // Verify permissions.
        const options = { mode: 'readwrite' };

        if (await directory.queryPermission(options) !== 'granted') {

            if (await directory.requestPermission(options) !== 'granted') {
                throw new Error('Write permission denied');
            }
        }

        // Remember directory for subsequent saves.
        await storeFileHandle(key, directory);

        // Retrieve all project languages.
        const langs = await jget('api.php?action=languages');

        const extensions = {
            csvsingle: 'csv',
            androidxml: 'xml',
            po: 'po'
        };

        const ext = extensions[format];

        if (!ext) {
            throw new Error('Unsupported batch save format');
        }

        // Export each language.
        for (const lang of langs) {

            const filename = `${lang.code}.${ext}`;

            status.textContent =
                `Saving ${saved + failed.length + 1}/${langs.length}: ${filename}`;

            try {

                const handle = await directory.getFileHandle(
                    filename,
                    { create: true }
                );

                await writeLanguageFile(
                    handle,
                    format,
                    lang.code
                );

                saved++;

            } catch (err) {
                failed.push(lang.code);
                console.error(err);
            }
        }

        status.textContent =
            `Saved ${saved}/${langs.length} files`;

        if (failed.length) {
            alert('Failed languages: ' + failed.join(', '));
        }

    } catch (err) {

        if (err.name === 'AbortError') {
            status.textContent = '';
        } else {
            status.textContent = 'Save failed';
            alert(err.message);
            console.error(err);
        }

    } finally {
        btn.disabled = false;
        singleBtn.disabled = false;
    }
}