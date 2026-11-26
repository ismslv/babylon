var user = null;
var users = [];

var config = {};

document.getElementById('pwd').onclick = async () => {
    const old = prompt('Current password:'); if (old === null) return;
    const neo = prompt('New password:'); if (neo === null) return;
    const res = await jpost('api.php?action=change_password', { old, new: neo });
    alert(res.success
        ? 'Password changed'
        : (res.error || 'Unknown error'));
};
async function jget(url) { return (await fetch(url)).json() }
async function jpost(url, obj) {
    return (await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(obj) })).json()
}
function autoResize(textarea) {
    textarea.style.height = '0px';
    textarea.style.height = textarea.scrollHeight + 'px';
}

async function loadUsers() {
    users = await jget('api.php?action=users');
    document.getElementById('userListSidebar').innerHTML =
        users.map(u => `<li class="user-li">${u.username} ${u.online ? '●' : '○'}
        <br/>${u.role == 'admin' ? '<span class="admin">⁜</span>' : ''}<small>[${u.langs.join(', ')}]</small></li>`).join('');
    user = users.find(u => u.username == current_user);
}
let activeLang = null;

async function loadLangs() {
    const langs = await jget('api.php?action=languages');
    const ul = document.getElementById('langListSidebar');
    ul.innerHTML = langs.map(l => {
        const pct = Math.round(l.progress);
        const done = l.done, total = l.total;
        return `
        <li class="lang-li" data-code="${l.code}">
        <div>${l.name == 'EN' ? 'English' : l.name}</div>
        <div class="lang-progress">${done}/${total}</div>
        <div class="bar">
            <div class="fill" style="width:${pct}%;"></div>
        </div>
        </li>`;
    }).join('');
    ul.querySelectorAll('li').forEach(li => {
        li.onclick = () => {   // highlight active
            activeLang = li.dataset.code;
            loadStrings();      // reload central panel
        };
    });
    if (!activeLang && langs.length) {
        if (user && user.langs && user.langs.length) activeLang = user.langs[0];
        else activeLang = langs[0].code;
        loadStrings();
    }
}
async function loadStrings() {
    if (!activeLang) return;
    const resp = await jget('api.php?action=strings&lang=' + encodeURIComponent(activeLang));
    const editOK = resp.editable;
    const isBase = activeLang === config.base_language;
    const commentOK = isBase && resp.can_comment;
    const rows = resp.rows;
    const box = document.getElementById('stringsList');
    const minVer = parseFloat(document.getElementById('verFilter').value || '1.0');

    allVersions.clear();
    rowsFiltered = resp.rows.filter(r => {
        allVersions.add(r.version);
        return parseFloat(r.version) >= minVer;
    });
    box.innerHTML = rowsFiltered.map(s => `
    <div class="string">
        <div class="string_left">
        ${config.use_versions ? `<span class="ver-badge">[${s.version}]</span><br/>` : ''}
        <div class="string-key" meta-key="${escapeHTML(s.key)}">${config.split_keys ? "<div>" + escapeHTML(s.key).replaceAll(config.split_keys, "</div><div>") + "</div>" : escapeHTML(s.key)}</div>
        </div>
        <div class="string_right">
            ${!isBase && s.comment
                ? `<div class="comment-inp-container"><div class="string-comment">${escapeHTML(s.comment)}</div></div>`
                : ''}

            ${s.english
                ? `<div class="string-en">${escapeHTML(s.english)}</div>`
                : ''}
            
            ${commentOK
                ? `<div class="comment-inp-container"><textarea class="comment-inp"
                        data-id="${s.id}"
                    >${escapeHTML(s.comment || '')}</textarea></div>`
                : ''}

            <textarea class="string-inp"
                data-lang="${activeLang}"
                data-id="${s.id}"
                ${editOK ? '' : 'readonly'}
            >${escapeHTML(s.translation || '')}</textarea>
            
            <label class="fuzzyLbl"><input type="checkbox" class="fuzzy-box" data-id="${s.id}"> fz</label>
            ${activeLang == config.base_language && editOK ? '<button onclick="deleteString('+s.id+')">delete</button>' : ''}
        </div>
    </div>
    `).join('');
    box.querySelectorAll('.string-inp').forEach(inp => {
        if (editOK) {
            inp.addEventListener('input', function () {
                autoResize(this);
            });
            inp.addEventListener('change', async e => {
                const sid = +inp.dataset.id;
                const val = inp.value;
                const savingEl = document.getElementById('saving');
                savingEl.style.display = 'inline';

                inp.classList.add('saving');
                const res = await jpost('api.php?action=save_translation', {
                    string_id: sid, value: val, lang: activeLang
                });

                inp.classList.remove('saving');
                savingEl.style.display = 'none';
                if (res.success) {
                    inp.classList.remove('untranslated');
                    inp.classList.add('translated');
                    let tr = updateUntranslatedCount();
                    updateLanguageCounter(activeLang, tr);
                }
            });
        }
        autoResize(inp);
    });
    box.querySelectorAll('.comment-inp').forEach(inp => {
        inp.addEventListener('input', () => {
            autoResize(inp);
        });

        inp.addEventListener('change', async () => {

            const savingEl = document.getElementById('saving');

            savingEl.style.display = 'inline';
            inp.classList.add('saving');

            try {
                const res = await jpost(
                    'api.php?action=save_comment',
                    {
                        string_id: +inp.dataset.id,
                        comment: inp.value
                    }
                );

                if (!res.success) {
                    throw new Error(res.error || 'Cannot save comment');
                }

            } catch (err) {
                alert(err.message);

            } finally {
                inp.classList.remove('saving');
                savingEl.style.display = 'none';
            }
        });

        autoResize(inp);
    });
    box.querySelectorAll('.string').forEach(row => {
        const input = row.querySelector('.string-inp');
        const check = row.querySelector('.fuzzy-box');
        const sid = +input.dataset.id;

        if (check && resp.rows) {
            const match = resp.rows.find(x => x.id === sid);
            if (match && +match.fuzzy) {
                check.checked = true;
                input.classList.add('fuzzy');
            }
        }

        check?.addEventListener('change', async () => {
            const fuzzy = check.checked ? 1 : 0;
            const res = await jpost('api.php?action=mark_fuzzy', {
                string_id: sid, lang: activeLang, fuzzy
            });
            input.classList.toggle('fuzzy', fuzzy === 1);
            updateUntranslatedCount();
        });
    });
    updateUntranslatedCount();
    
    const previousVersion = document.getElementById('verFilter').value || '1.0';

    allVersions.add('1.0');
    buildVersionFilter();

    if (allVersions.has(previousVersion)) {
        document.getElementById('verFilter').value = previousVersion;
    }

    document.querySelectorAll('.lang-li').forEach(inp => {
        if (inp.getAttribute('data-code') === activeLang) inp.classList.add('active');
        else inp.classList.remove('active');
    })
    filterByKey();
}
document.addEventListener('DOMContentLoaded', () => {
    getConfig();
    loadUsers();
    loadLangs();
});

function updateUntranslatedCount() {
    const all = document.querySelectorAll('.string-inp');
    let un = 0, fz = 0, tr = 0;

    all.forEach(inp => {
        const val = inp.value.trim();
        const fuzzy = inp.classList.contains('fuzzy');

        inp.classList.remove('translated', 'untranslated');

        if (!val) {
            inp.classList.add('untranslated');
            un++;
        } else if (fuzzy) {
            inp.classList.add('fuzzy');
            fz++;
            tr++;
        } else {
            inp.classList.add('translated');
            tr++;
        }
    });

    document.getElementById('unCount').textContent = un;
    document.getElementById('fuzzyCount').textContent = fz;
    return tr;
}

function updateLanguageCounter(lang, translated) {
    const count = document.querySelector('.lang-li[data-code="' + lang + '"] .lang-progress');
    if (count) {
        var _count = count.textContent.split('/');
        count.textContent = translated + '/' + +_count[1];
        const bar = document.querySelector('.lang-li[data-code="' + lang + '"] .bar .fill');
        bar.style.width = Math.round((translated) / +_count[1] * 100) + '%';
    }
}

let unIdx = 0;
function scrollToUntranslated(dir) {
    const un = Array.from(
        document.querySelectorAll('.string-inp.untranslated')
    ).filter(inp => inp.closest('.string').style.display !== 'none');
    if (un.length === 0) return;
    unIdx = unIdx < 0
        ? (dir > 0 ? 0 : un.length - 1)
        : (unIdx + dir + un.length) % un.length;
    un[unIdx].scrollIntoView({ behavior: 'smooth', block: 'center' });
    un[unIdx].focus();
}
let fuIdx = 0;
function scrollToFuzzy(dir) {
    const un = Array.from(
        document.querySelectorAll('.string-inp.fuzzy')
    ).filter(inp => inp.closest('.string').style.display !== 'none');
    if (un.length === 0) return;
    fuIdx = fuIdx < 0
        ? (dir > 0 ? 0 : un.length - 1)
        : (fuIdx + dir + un.length) % un.length;
    un[fuIdx].scrollIntoView({ behavior: 'smooth', block: 'center' });
    un[fuIdx].focus();
}

document.getElementById('prevUn').onclick = () => scrollToUntranslated(-1);
document.getElementById('nextUn').onclick = () => scrollToUntranslated(1);
document.getElementById('prevFu').onclick = () => scrollToFuzzy(-1);
document.getElementById('nextFu').onclick = () => scrollToFuzzy(1);

let allVersions = new Set(['1.0']);

function buildVersionFilter() {
    const sel = document.getElementById('verFilter');
    sel.innerHTML = [...allVersions].sort((a, b) => parseFloat(a) - parseFloat(b))
        .map(v => `<option value="${v}">${v}+ ▾</option>`).join('');
    sel.value = '1.0';
    sel.onchange = loadStrings;
}

function escapeHTML(str) {
    return (str || '').replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function getConfig() {
    fetch('api.php?action=config')
        .then(r => r.json()).then(cfg => {
            config = cfg;
            setUIfromConfig();
            initQuickSave();
        });
}

function setUIfromConfig() {
    document.querySelector('.logo_t2').textContent = config.project_name;
    document.title = "Babylon • " + config.project_name;
    document.getElementById('verFilter').style.display = config.use_versions ? 'block' : 'none';
    loadRules();

    if (config.custom_names) {
        if (config.custom_names.languages)
            document.getElementById('tr_langs').textContent = config.custom_names.languages;
        if (config.custom_names.translators)
            document.getElementById('tr_translators').textContent = config.custom_names.translators;
        if (config.custom_names.greeting)
            document.getElementById('tr_greeting').textContent = config.custom_names.greeting.replace('username', current_user);
        if (config.custom_names.password)
            document.getElementById('pwd').textContent = config.custom_names.password;
        if (config.custom_names.admin)
            document.getElementById('tr_admin').textContent = config.custom_names.admin;
        if (config.custom_names.exit)
            document.getElementById('tr_exit').textContent = config.custom_names.exit;
    }
}

function deleteString(sid) {
    fetch('api.php?action=string_delete', {
      method: 'POST',
      body: new URLSearchParams({ id: sid })
    })
      .then(r => r.json())
      .then(res => {
        loadStrings();
      });
}

function filterByKey() {
    const query = document.getElementById('keySearch')
        .value.trim().toLowerCase();

    document.querySelectorAll('#stringsList .string').forEach(row => {
        const key = row.querySelector('.string-key').getAttribute("meta-key").toLowerCase();

        const visible = key.includes(query);

        row.style.display = visible ? '' : 'none';

        if (visible) {
            row.querySelectorAll('.string-inp, .comment-inp')
                .forEach(autoResize);
        }
    });

    unIdx = -1;
    fuIdx = -1;
}

const newStringBtn = document.getElementById('newString');

if (newStringBtn) {
    const dialog = document.getElementById('newStringDialog');
    const form = document.getElementById('newStringForm');
    const error = document.getElementById('newStringError');
    const submit = document.getElementById('newStringSubmit');

    newStringBtn.onclick = () => {
        form.reset();
        error.textContent = '';

        document.getElementById('newStringVersionBlock').hidden =
            !config.use_versions;

        dialog.showModal();
        document.getElementById('newStringKey').focus();
    };

    document.getElementById('newStringCancel').onclick = () => {
        if (!submit.disabled) dialog.close();
    };

    dialog.addEventListener('cancel', e => {
        if (submit.disabled) e.preventDefault();
    });

    form.onsubmit = async e => {
        e.preventDefault();

        const key = document.getElementById('newStringKey').value.trim();
        const value = document.getElementById('newStringValue').value;
        const version = document.getElementById('newStringVersion').value;

        error.textContent = '';
        submit.disabled = true;

        try {
            const res = await jpost('api.php?action=new_string', {
                key,
                value,
                version
            });

            if (!res.success) {
                error.textContent = res.error || 'Cannot create string';
                return;
            }

            dialog.close();

            try {
                // Switch to base language.
                activeLang = config.base_language || 'en';

                // Reset search and version filters.
                document.getElementById('keySearch').value = '';
                document.getElementById('verFilter').value = '1.0';

                // Refresh language progress and string list.
                await loadLangs();
                await loadStrings();

                // Find and scroll to the new string.
                const inp = document.querySelector(
                    `.string-inp[data-id="${res.id}"]`
                );

                if (inp) {
                    inp.closest('.string').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    inp.focus();
                }
            } catch (err) {
                alert('String created, but refreshing failed. Reload the page.');
                console.error(err);
            }

        } catch (err) {
            error.textContent = 'Server error. Check whether the key was created.';
            console.error(err);
        } finally {
            submit.disabled = false;
        }
    };
}

function loadRules() {
    const rules = config.translation_rules;
    const section = document.getElementById('rulesSidebar');
    const list = document.getElementById('rulesListSidebar');

    list.replaceChildren();

    if (!Array.isArray(rules) || !rules.length) {
        section.hidden = true;
        return;
    }

    for (const rule of rules) {
        const li = document.createElement('li');
        li.textContent = rule;
        list.appendChild(li);
    }

    section.hidden = false;
}