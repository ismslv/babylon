var config = {};

var activeLang = null;
var activeUser = null;
var activeFormat = null;

var toolTitle = document.getElementById('tool_title');
var toolTitleText = document.getElementById('tool_title_text');
var toolSave  = document.getElementById('tool_save');
var toolSaveText = document.getElementById('button_save');
var toolDelete  = document.getElementById('tool_delete');
var toolDeleteText = document.getElementById('button_delete');

var formUser = document.getElementById('form_user');
var formLang = document.getElementById('form_language');
var formFormat = document.getElementById('form_format');

var fileInput = document.getElementById('file_import');

var allLangs = [];
var allUsers = [];

document.addEventListener('DOMContentLoaded', () => {
  getConfig();
  loadLanguages();
  loadUsers();
  loadFormats();

  toolSave.onclick = onSaveTool;
  toolDelete.onclick = onDeleteTool;

  document.getElementById('sidebar_button_new_lang').onclick = () => {
    activeLang = null;
    deselectAll();
    showEditLang();
  };

  document.getElementById('sidebar_button_new_user').onclick = () => {
    activeUser = null;
    deselectAll();
    showEditUser();
  };
});

function loadLanguages() {
  fetch('api.php?action=languages')
    .then(r => r.json())
    .then(langs => {
      allLangs = langs;

      const ul = document.getElementById('langListSidebar');
      ul.innerHTML = langs.map(l => {
          const pct = Math.round(l.progress);
          const done = l.done, total = l.total;
          return `
          <li class="lang-li" data-id="${l.id}">
          <div>${l.name == 'EN' ? 'English' : l.name}</div>
          <div class="lang-progress">${done}/${total}</div>
          <div class="bar">
              <div class="fill" style="width:${pct}%;"></div>
          </div>
          </li>`;
      }).join('');
      ul.querySelectorAll('li').forEach(li => {
          li.onclick = () => {
              activeLang = li.dataset.id;
              deselectAll();
              document.querySelector('.lang-li[data-id="'+activeLang+'"]').classList.add('active');
              showEditLang();
          };
      });

      const c = document.getElementById('ulangs');
      c.innerHTML = langs.map(l => {
        return `<label><input class="ulang-check" type="checkbox" value="${l.code}"/> ${l.name} (${l.code})</label><br/>`;
      }).join('');
    });
}

function showEditLang() {
  activeUser = null;
  activeFormat = null;
  formLang.style.display = 'block';
  var code = "";
  var name = "";
  var lang = allLangs.find(l => l.id == activeLang);
  if (lang) {
    code = lang.code;
    name = lang.name;
  }
  document.getElementById('lcode').value = code;
  document.getElementById('lname').value = name;

  toolTitle.style.display = 'inline-block';
  toolSave.style.display = 'inline-block';
  toolDelete.style.display = 'inline-block';

  toolTitleText.textContent = lang ? 'editing language' : 'adding language';
  toolSaveText.textContent = lang ? 'save' : 'add';
  toolDeleteText.textContent = lang ? 'delete' : 'cancel';
}

function showEditUser() {
  activeLang = null;
  activeFormat = null;
  formUser.style.display = 'block';

  var name = "";
  var role = "translator";
  var user = allUsers.find(u => u.id == activeUser);
  if (user) {
    name = user.username;
    role = user.role;
  }
  document.getElementById('uname').value = name;
  document.getElementById('urole').value = role;

  document.querySelectorAll('.ulang-check').forEach(inp => {
    inp.checked = user ? user.langs.includes(inp.value) : false;
  });

  toolTitle.style.display = 'inline-block';
  toolSave.style.display = 'inline-block';
  toolDelete.style.display = 'inline-block';

  toolTitleText.textContent = user ? 'editing user' : 'adding user';
  toolSaveText.textContent = user ? 'save' : 'add';
  toolDeleteText.textContent = user ? 'delete' : 'cancel';
}

function showEditFormat() {
  activeLang = null;
  activeUser = null;
  formFormat.style.display = 'block';

  getFormatMenu();
}

function onSaveTool() {
  if (activeLang != null) {
    // save active lang
    fetch('api.php?action=lang_rename', {
      method: 'POST',
      body: new URLSearchParams({
        id: activeLang,
        code: document.getElementById('lcode').value,
        name: document.getElementById('lname').value
      })
    })
    .then(r => r.json()).then(() => {
        activeLang = null;
        loadLanguages();
        deselectAll();
      });
  } else if (activeUser != null) {
    // save active user
    const checks = document.querySelectorAll('.ulang-check:checked');
    const langs = Array.from(checks).map(cb => cb.value);
    const params = new URLSearchParams();
    params.append('id', activeUser);
    params.append('name', document.getElementById('uname').value);
    params.append('role', document.getElementById('urole').value);
    langs.forEach(l => params.append('langs[]', l));

    fetch('api.php?action=user_rename', {
      method: 'POST',
      body: params
    })
      .then(r => r.json()).then(() => {
        activeUser = null;
        loadUsers();
        deselectAll();
      });
  } else if (formLang.style.display == 'block') {
    // add new lang
    fetch('api.php?action=add_language', {
      method: 'POST',
      body: new URLSearchParams({
        code: document.getElementById('lcode').value,
        name: document.getElementById('lname').value
      })
    })
      .then(r => r.json()).then(() => {
        activeLang = null;
        loadLanguages();
        deselectAll();
      });
  } else if (formUser.style.display == 'block') {
    // add new user
    const checks = document.querySelectorAll('.ulang-check:checked');
    const langs = Array.from(checks).map(cb => cb.value);
    const params = new URLSearchParams();
    params.append('name', document.getElementById('uname').value);
    params.append('role', document.getElementById('urole').value);
    langs.forEach(l => params.append('langs[]', l));

    fetch('api.php?action=add_user', {
      method: 'POST',
      body: params
    })
      .then(r => r.json()).then(() => {
        activeUser = null;
        loadUsers();
        deselectAll();
      });
  }
}

function onDeleteTool() {
  if (activeLang != null) {
    fetch('api.php?action=lang_delete', {
      method: 'POST',
      body: new URLSearchParams({ id: activeLang })
    }).then(r => r.json()).then(() => {
      activeLang = null;
      loadLanguages();
      deselectAll();
    });
  } else if (activeUser != null) {
    fetch('api.php?action=user_delete', {
      method: 'POST',
      body: new URLSearchParams({ id: activeUser })
    }).then(r => r.json()).then(() => {
      activeUser = null;
      loadUsers();
      deselectAll();
    });
  }
}

function loadUsers() {
  fetch('api.php?action=users')
    .then(r => r.json())
    .then(users => {
      allUsers = users;
      const ul = document.getElementById('userListSidebar');
      ul.innerHTML =
        users.map(u => `<li class="user-li" data-id="${u.id}">${u.username} ${u.online ? '●' : '○'}
        <br/>${u.role == 'admin' ? '<span class="admin">⁜</span>' : ''}<small>[${u.langs.join(', ')}]</small></li>`).join('');
      ul.querySelectorAll('li').forEach(li => {
          li.onclick = () => {
              activeUser = li.dataset.id;
              deselectAll();
              document.querySelector('.user-li[data-id="'+activeUser+'"]').classList.add('active');
              showEditUser();
          };
      });
    });
}

function loadFormats() {
  fetch('api.php?action=formats')
    .then(r => r.json())
    .then(formats => {
      const ul = document.getElementById('formatListSidebar');
      ul.innerHTML = formats.map(f => {
          return `<li class="format-li" data-id="${f.id}">${f.name}<br/><small>[${f.extension}]</small></li>`;
      }).join('');
      ul.querySelectorAll('li').forEach(li => {
          li.onclick = () => {
              activeFormat = li.dataset.id;
              deselectAll();
              document.querySelector('.format-li[data-id="'+activeFormat+'"]').classList.add('active');
              showEditFormat();
          };
      });
    });
}

function button(txt, fn) {
  const b = document.createElement('button');
  b.textContent = txt;
  b.onclick = fn;
  return b;
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

    if (config.custom_names) {
        if (config.custom_names.languages)
            document.getElementById('tr_langs').textContent = config.custom_names.languages;
        if (config.custom_names.translators)
            document.getElementById('tr_translators').textContent = config.custom_names.translators;
        if (config.custom_names.greeting)
            document.getElementById('tr_greeting').textContent = config.custom_names.greeting.replace('username', current_user);
        if (config.custom_names.public)
            document.getElementById('tr_public').textContent = config.custom_names.public;
        if (config.custom_names.exit)
            document.getElementById('tr_exit').textContent = config.custom_names.exit;
    }
}

function getFormatMenu() {
    fetch('api.php?action=format_menu', {
      method: 'POST',
      body: new URLSearchParams({format: activeFormat})
    })
        .then(r => r.json()).then(menu_cfg => {
          renderFormatMenu(menu_cfg);
        });
}

function renderFormatMenu(menuCfg) {
  formFormat.innerHTML = '';
  var html = "";
  menuCfg.forEach(item => {
    switch (item[0]) {
      case "text":
        if (item.length >= 2) {
          var text = item[1];
          // match all [] by regexp
          text = text.replace(/\[(.*?)\]/g, function (match, p1) {
            p1 = p1.split('.');
            if (config[p1[0]] == undefined) return match;
            var c = config[p1[0]];
            for (var i = 1; i < p1.length; i++) {
              if (c[p1[i]] == undefined) return match;
              c = c[p1[i]];
            }
            return c || match;
          });
          html += '<p>' + text + '</p>';
        }
        break;
      
      case "zip":
        if (item.length >= 2) {
          html += '<p><button onclick="downloadZip(\'' + activeFormat + '\')">' + item[1] + '</button></p>';
        }
        break;
      
      case "import":
        if (item.length >= 2) {
          html += '<p><button onclick="upload(\'' + activeFormat + '\', null)">' + item[1] + '</button></p>';
        }
        break;
      
      case "export":
        if (item.length >= 2) {
          html += '<p><button onclick="download(\'' + activeFormat + '\')">' + item[1] + '</button></p>';
        }
        break;
      
      case "by_lang_import":
        if (item.length >= 2) {
          html += allLangs.map(l => {
            var b = ' <button onclick="upload(\'' + activeFormat + '\', \'' + l.code + '\')">' + item[1] + '</button>';
            return '<p>' + l.code + b + '</p>';
          }).join('');
        }
        break;
      
      case "by_lang_export":
        if (item.length >= 2) {
          html += allLangs.map(l => {
            var b = ' <button onclick="downloadByLang(\'' + activeFormat + '\', \'' + l.code + '\')">' + item[1] + '</button>';
            return '<p>' + l.code + b + '</p>';
          }).join('');
        }
        break;
      
      case "by_lang_import_export":
        if (item.length >= 3) {
          html += allLangs.map(l => {
            var b1 = ' <button onclick="upload(\'' + activeFormat + '\', \'' + l.code + '\')">' + item[1] + '</button>';
            var b2 = ' <button onclick="downloadByLang(\'' + activeFormat + '\', \'' + l.code + '\')">' + item[2] + '</button>';
            return '<p>' + l.code + b1 + b2 + '</p>';
          }).join('');
        }
        break;
      
      default:
        break;
    }
  })
  formFormat.innerHTML = html;
}

function downloadZip(fmt) {
  window.location = `api.php?action=export_zip&format=${fmt}`;
}

function downloadByLang(fmt, lang) {
  window.location = `api.php?action=export&format=${fmt}&lang=${lang}`;
}

function download(fmt) {
  window.location = `api.php?action=export&format=${fmt}`;
}

function upload(fmt, lang) {
  fileInput.onchange = () => {
    const fd = new FormData();
    fd.append('format', fmt);
    if (lang != null)
      fd.append('lang', lang);
    fd.append('file', fileInput.files[0]);

    fetch('api.php?action=import', {
      method: 'POST',
      body: fd
    })
      .then(r => r.json())
      .then(res => {
        console.log(res);
        activeFormat = null;
        loadLanguages();
        deselectAll();
      });
  };
  fileInput.click();
}

function deselectAll() {
  document.querySelectorAll('li.active').forEach(l => l.classList.remove('active'));
  formUser.style.display = 'none';
  formFormat.style.display = 'none';
  formLang.style.display = 'none';
  toolTitle.style.display = 'none';
  toolSave.style.display = 'none';
  toolDelete.style.display = 'none';
}