var user = null;
var users = [];

var config = {};

document.getElementById('pwd').onclick = async () => {
    const old = prompt('Current password:'); if (old === null) return;
    const neo = prompt('New password:'); if (neo === null) return;
    const res = await jpost('api.php?action=change_password', { old, new: neo });
    alert(res.success ? 'Password changed' : 'Wrong current password');
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
        <div class="string-key">${s.key}</div><br/>
        <label class="fuzzyLbl"><input type="checkbox" class="fuzzy-box" data-id="${s.id}"> fuzzy</label>
        ${activeLang == config.base_language && editOK ? '<button onclick="deleteString('+s.id+')">delete</button>' : ''}
        </div>
        <div class="string_right">
        ${s.english ? `<div class="string-en">${s.english}</div>` : ''}
        <textarea class="string-inp" type="text" data-lang="${activeLang}" data-id="${s.id}" height="auto" ${editOK ? '' : 'readonly'}>${escapeHTML(s.translation || '')}</textarea>
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
    if (!document.getElementById('verFilter').options.length) buildVersionFilter();
    document.querySelectorAll('.lang-li').forEach(inp => {
        if (inp.getAttribute('data-code') === activeLang) inp.classList.add('active');
        else inp.classList.remove('active');
    })
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
    const un = Array.from(document.querySelectorAll('.string-inp.untranslated'));
    if (un.length === 0) return;
    unIdx = (unIdx + dir + un.length) % un.length;
    un[unIdx].scrollIntoView({ behavior: 'smooth', block: 'center' });
    un[unIdx].focus();
}
let fuIdx = 0;
function scrollToFuzzy(dir) {
    const un = Array.from(document.querySelectorAll('.string-inp.fuzzy'));
    if (un.length === 0) return;
    fuIdx = (fuIdx + dir + un.length) % un.length;
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
        });
}

function setUIfromConfig() {
    document.querySelector('.logo_t2').textContent = config.project_name;
    document.title = "Babylon • " + config.project_name;
    document.getElementById('verFilter').style.display = config.use_versions ? 'block' : 'none';

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