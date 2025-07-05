<p align="center">
  <img src="https://thewizard.space/images/logo_babylon.gif" width="100px"/>
  <br/>
  <img src="https://thewizard.space/images/babylon_text.png" width="140px"/>
</p>

Babylon is a lightweight collaborative translation tool for creative projects — cozy, minimal, and in the retro-futuristic style of [The Wizard](thewizard.space).

Use it to organize and collaborate on translations for games, apps, stories, or anything else that needs localization.

Supports multiple languages, fuzzy matches, and a charming pixel-art interface that feels right at home in the TrinketOS universe.

Free to use, easy to host, and ready to help your words cross borders.

⸻

### Features

- Web-based, clean & minimal UI
- SQLite backend — no external DB needed
- Support for 10–15 translators and ~1000 strings without performance issues
- Modular support for file formats:
	- Android XML
	- CSV (Godot, Unity-style)
	- PO/POT (gettext)
- Import & export translations per language or all languages at once in zip archive
- Supports versioning of strings (optional)
- Per-language assignment for translators
- Admin panel for user & language management
- Login-based, with simple password system
- Real-time online presence tracking
- Customizable UI labels (custom_names) to adapt terminology to your team or project.

⸻

### Installation

1. Upload the code to your PHP server (works with PHP 7+)
2. Make sure the folder is writable (to create the translations.db)
3. Create config.json file
4. Login as admin with password admin. On first load, the system initializes the database and prefills it from config.json if available
5. Change your password in public area
6. Start adding languages and users in admin area

⸻

### Configuration

All settings are in config.json. Use `sample_config.json` as a reference. All settings are optional: without any config, default language will be en and user admin will be created.

Options:

<table>
<tr>
<td>Key</td>
<td>Description</td>
</tr>
<tr>
<td>project_name</td>
<td>Name of your project (shown in UI).</td>
</tr>
<tr>
<td>use_versions</td>
<td>Enable string versioning (true/false).</td>
</tr>
<tr>
<td>languages</td>
<td>List of language codes & names.</td>
</tr>
<tr>
<td>users</td>
<td>Initial users with roles & assigned languages.</td>
</tr>
<tr>
<td>config_csv</td>
<td>CSV-specific settings (delimiter, key column).</td>
</tr>
<tr>
<td>custom_names</td>
<td>Custom UI labels.</td>
</tr>
</table>

Passwords for all users default to their username at first login.
Users can change their password afterwards.

⸻

### Usage

#### Admin Area

Accessible via admin.php.
Here you can:
- Manage languages (add, rename, delete).
- Manage users (add, rename, delete, assign languages, change roles).
- Manage files (import/export, per-language or ZIP, multiple formats).

#### Translation Area

Accessible via index.php.
- Translators see only the languages assigned to them.
- Sidebar shows who’s online.
- Strings are listed with their keys, base language source, and input field for translations.
- Untranslated & fuzzy strings are highlighted.
- Versions are displayed if enabled.

⸻

### File Formats

#### Android XML
- Standard <string name="key">value</string> & <string-array>.
- Versions stored as version="1.0" attributes.

#### CSV
- Supports Godot (keys,en,fr,ja) and Unity-style (Key,English(en),French(fr)).
- Multi-language per file.
- Configurable delimiter and key column.

#### PO/POT
- Standard gettext .po & .pot files.
- Base language exported as .pot template.
- .po files include translations per language.

⸻

### Customization

#### UI Labels

If you want to add your own flair, you can define custom_names in config.json to adapt the terminology:

<table>
<tr>
<td>Key</td>
<td>Meaning</td>
</tr><tr>
<td>languages</td>
<td>Column heading for languages</td>
</tr><tr>
<td>translators</td>
<td>Column heading for users</td>
</tr><tr>
<td>greeting</td>
<td>Greeting in header</td>
</tr><tr>
<td>password</td>
<td>Password change button</td>
</tr><tr>
<td>admin</td>
<td>Link to admin area</td>
</tr><tr>
<td>public</td>
<td>Link to public area</td>
</tr><tr>
<td>exit</td>
<td>Logout link</td>
</tr>
</table>

#### Modules

File format modules live in formats/.
You can implement your own by extending FormatInterface and adding it there.

⸻

### Development

Modules declare their UI menu via: `public static function menu(): array;`

Example:


```php
public static function menu(): array {
  return [
    ['text', 'PO/POT (gettext) — English strings are keys'],
    ['by_lang_import_export', 'import', 'export'],
    ['zip', 'export all languages as .po in zip']
  ];
}

```

Supported menu tags:

<table>
<tr>
<td>Tag</td>
<td>Meaning</td>
</tr><tr>
<td>text</td>
<td>Informational text (placeholders replaced from config)</td>
</tr><tr>
<td>import</td>
<td>Generic import</td>
</tr><tr>
<td>export</td>
<td>Generic export</td>
</tr><tr>
<td>by_lang_import</td>
<td>Per-language import</td>
</tr><tr>
<td>by_lang_export</td>
<td>Per-language export</td>
</tr><tr>
<td>by_lang_import_export</td>
<td>Both</td>
</tr><tr>
<td>zip</td>
<td>Export all in a ZIP</td>
</tr>
</table>

⸻

### License

Babylon is free software created for translators and creators, not for businessmen or corporate entities.

✅ You are free to:
- Use Babylon for any free or commercial project you’re translating.
- Modify Babylon for your own use.
- Share screenshots, showcase it, and talk about it.

🚫 You are not allowed to:
- Sell Babylon as your product.
- Redistribute Babylon (or modified versions) as your own software or service.

If you modify it significantly and want to share your changes, please contact me or link back to the original project so everyone knows where it came from.

Made with ☕ & ✨ by [The Wizard](thewizard.space)

Happy translating!

If you like Babylon, feel free to ⭐ the repo and send PRs with improvements or new modules.
