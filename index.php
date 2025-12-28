<?php
// index.php
// BACKEND: Scan server-side apps

$appsDir = __DIR__ . '/apps';
$appList = [];

function resolveIcon($path, $webPath) {
    $extensions = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
    foreach ($extensions as $ext) {
        if (file_exists("$path/icon.$ext")) return "$webPath/icon.$ext";
    }
    return 'default';
}

if (file_exists($appsDir) && is_dir($appsDir)) {
    $items = scandir($appsDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $localPath = "$appsDir/$item";
        $webPath = "apps/$item";
        if (is_dir($localPath)) {
            $namePath = "$localPath/name.txt";
            $name = file_exists($namePath) ? trim(file_get_contents($namePath)) : ucfirst($item);
            
            $entry = null;
            if (file_exists("$localPath/index.php")) $entry = "$webPath/index.php";
            elseif (file_exists("$localPath/index.html")) $entry = "$webPath/index.html";

            if ($entry) {
                $appList[] = [
                    'id' => $item,
                    'name' => htmlspecialchars($name),
                    'icon' => resolveIcon($localPath, $webPath),
                    'url' => $entry,
                    'type' => 'local'
                ];
            }
        }
    }
}

// System Settings
$appList[] = [
    'id' => 'settings',
    'name' => 'Settings',
    'icon' => 'settings_icon',
    'url' => 'internal:settings',
    'type' => 'system'
];

$serverPayload = json_encode($appList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Pixel OS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

    <style>
        :root {
            /* Default Theme */
            --primary: #9aa0a6;
            --accent: #a8c7fa; 
            --on-accent: #000000;
            --surface: #fdf8fd;
            --surface-variant: #f0f4f8;
            --on-surface: #1f1f1f;
            --font-stack: 'Outfit', sans-serif;
            --ui-scale: 1;
            
            /* Motion */
            --ease-elastic: cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --ease-out: cubic-bezier(0.2, 0, 0, 1);
            --duration: 0.35s;
        }

        body.dark {
            --surface: #131314;
            --surface-variant: #303030;
            --on-surface: #e3e3e3;
            --primary: #444746;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body {
            margin: 0; padding: 0; width: 100vw; height: 100dvh; overflow: hidden;
            font-family: var(--font-stack);
            background-color: var(--surface);
            background-image: url('defaultwallpaper.png');
            background-size: cover; background-position: center;
            color: var(--on-surface);
            user-select: none; -webkit-user-select: none;
            touch-action: none;
            transition: filter 0.2s;
        }

        /* --- LAYOUT --- */
        #os-root {
            display: flex; flex-direction: column; height: 100%;
            padding-top: env(safe-area-inset-top);
            zoom: var(--ui-scale);
        }

        .status-bar {
            height: 50px; display: flex; justify-content: space-between; align-items: center;
            padding: 0 24px; font-weight: 600; font-size: 14px;
            color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            cursor: pointer; z-index: 50;
            transition: transform 0.2s;
        }
        .status-bar:active { transform: scale(0.99); opacity: 0.8; }

        .workspace {
            flex: 1; padding: 20px;
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            grid-auto-rows: 110px; gap: 10px;
            align-content: flex-start; overflow-y: auto;
        }

        .dock-container {
            padding: 16px; display: flex; justify-content: center; 
            padding-bottom: max(16px, env(safe-area-inset-bottom));
        }
        .dock {
            background: rgba(245, 245, 245, 0.4);
            backdrop-filter: blur(40px); -webkit-backdrop-filter: blur(40px);
            border-radius: 35px;
            padding: 12px; display: flex; gap: 16px; align-items: center;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
            min-height: 84px; transition: opacity 0.3s;
        }
        body.dark .dock { background: rgba(30, 30, 30, 0.6); }

        /* --- APPS --- */
        .app {
            display: flex; flex-direction: column; align-items: center;
            cursor: pointer; position: relative; width: 100%;
            transition: transform 0.2s;
        }
        .app:hover .app-icon { transform: scale(1.05); box-shadow: 0 6px 12px rgba(0,0,0,0.2); }
        .app:active .app-icon { transform: scale(0.9); }
        
        .app-icon {
            width: 60px; height: 60px; border-radius: 30px;
            background: var(--surface-variant);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative; overflow: hidden;
            transition: all 0.2s var(--ease-out);
        }
        .app-icon img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
        
        .app-name {
            margin-top: 8px; font-size: 13px; text-align: center;
            text-shadow: 0 1px 4px rgba(0,0,0,0.8); color: white;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 85px;
            font-weight: 500;
        }
        .dock .app-name { display: none; }

        /* Edit Mode */
        .remove-badge {
            position: absolute; top: -5px; right: -5px;
            background: #444; color: white; border-radius: 50%;
            width: 22px; height: 22px; display: flex; justify-content: center; align-items: center;
            font-size: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: none;
            z-index: 5;
        }
        body.edit-mode .app { animation: jiggle 0.3s infinite alternate; }
        body.edit-mode .remove-badge { display: flex; }
        @keyframes jiggle { from { transform: rotate(-2deg); } to { transform: rotate(2deg); } }

        .add-app-btn {
            width: 60px; height: 60px; border-radius: 30px;
            background: rgba(255,255,255,0.2); border: 2px dashed rgba(255,255,255,0.5);
            display: flex; align-items: center; justify-content: center;
            color: white; cursor: pointer; display: none;
        }
        body.edit-mode .add-app-btn { display: flex; }

        /* --- ANIMATOR (Ghost element) --- */
        .animator {
            position: fixed; z-index: 100; pointer-events: none;
            overflow: hidden; border-radius: 30px;
            display: flex; align-items: center; justify-content: center;
            transform-origin: center;
        }
        .animator.fullscreen { border-radius: 0; }

        /* --- WINDOW --- */
        .window {
            position: fixed; inset: 0; z-index: 98;
            background: var(--surface);
            display: flex; flex-direction: column;
            opacity: 0; pointer-events: none;
        }
        .window.visible { opacity: 1; pointer-events: auto; }
        
        .win-header {
            height: 56px; display: flex; align-items: center; padding: 0 16px;
            background: var(--surface); border-bottom: 1px solid rgba(128,128,128,0.1);
        }
        .win-content { flex: 1; position: relative; background: var(--surface); overflow-y: auto; }
        iframe { width: 100%; height: 100%; border: none; }

        /* --- CONTROL CENTER --- */
        .shade {
            position: fixed; inset: 0; background: rgba(0,0,0,0.01); z-index: 200;
            opacity: 0; pointer-events: none; display: flex; justify-content: center; padding-top: 24px;
            transition: background var(--duration);
        }
        .shade.open { opacity: 1; pointer-events: auto; background: rgba(0,0,0,0.3); backdrop-filter: blur(15px); }
        
        .cc-panel {
            width: 90%; max-width: 420px;
            background: var(--surface); color: var(--on-surface);
            border-radius: 32px; padding: 24px;
            /* Exit State */
            transform: translateY(-50px) scale(0.9); opacity: 0;
            transition: all var(--duration) var(--ease-out);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .shade.open .cc-panel { 
            /* Enter State */
            transform: translateY(0) scale(1); opacity: 1;
        }

        .cc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 20px 0; }
        .cc-btn {
            aspect-ratio: 1.2/1; background: var(--surface-variant); 
            border-radius: 18px; display: flex; flex-direction: column;
            align-items: center; justify-content: center; cursor: pointer;
            transition: 0.2s var(--ease-out); position: relative;
        }
        .cc-btn:active { transform: scale(0.92); }
        .cc-btn.active { background: var(--accent); color: var(--on-accent); }
        .cc-label { font-size: 11px; margin-top: 4px; font-weight: 600; }
        
        .cc-slider {
            background: var(--surface-variant); height: 50px; border-radius: 25px;
            margin-bottom: 12px; display: flex; align-items: center; padding: 0 16px;
            position: relative; overflow: hidden;
        }
        .cc-slider input[type=range] {
            position: absolute; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2;
        }
        .slider-fill {
            position: absolute; left: 0; top: 0; bottom: 0; background: var(--accent);
            opacity: 0.5; z-index: 1; pointer-events: none; transition: width 0.1s;
        }

        /* --- SETTINGS UI --- */
        .settings-page { padding: 24px; max-width: 600px; margin: 0 auto; color: var(--on-surface); }
        .set-group { background: var(--surface-variant); border-radius: 20px; overflow: hidden; margin-bottom: 20px; }
        .set-item {
            padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid rgba(128,128,128,0.1);
        }
        .toggle-switch {
            width: 48px; height: 28px; background: #ccc; border-radius: 14px; position: relative; transition: 0.2s;
        }
        .toggle-switch::after {
            content:''; position: absolute; left: 4px; top: 4px; width: 20px; height: 20px;
            background: white; border-radius: 50%; transition: 0.2s;
        }
        .toggle-switch.active { background: var(--accent); }
        .toggle-switch.active::after { transform: translateX(20px); }
        
        /* Color Picker Input */
        input[type="color"] {
            border: none; width: 40px; height: 40px; border-radius: 50%; overflow: hidden; cursor: pointer; padding: 0;
            -webkit-appearance: none;
        }
        input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        input[type="color"]::-webkit-color-swatch { border: none; }

        /* --- MODALS --- */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: 0.3s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        
        .modal {
            background: var(--surface); width: 85%; max-width: 350px;
            padding: 24px; border-radius: 28px;
            transform: scale(0.9); opacity: 0; transition: 0.3s var(--ease-elastic);
        }
        .modal-overlay.open .modal { transform: scale(1); opacity: 1; }

        .modal input[type="text"] {
            width: 100%; padding: 12px; margin-bottom: 12px;
            background: var(--surface-variant); border: none; border-radius: 12px;
            color: var(--on-surface); font-family: inherit;
        }
        .btn {
            background: var(--accent); color: var(--on-accent); border: none;
            padding: 10px 20px; border-radius: 20px; font-weight: 600; cursor: pointer;
            transition: transform 0.1s;
        }
        .btn:active { transform: scale(0.95); }

    </style>
</head>
<body>

    <div id="os-root">
        <!-- Status Bar -->
        <div class="status-bar" id="status-bar">
            <span id="clock">12:00</span>
            <div style="display:flex; gap:12px; opacity:0.9;">
                <span class="material-symbols-rounded">wifi</span>
                <span class="material-symbols-rounded">battery_full</span>
            </div>
        </div>

        <!-- Desktop -->
        <div class="workspace" id="grid">
            <div class="add-app-btn" onclick="OS.openAddAppModal()">
                <span class="material-symbols-rounded">add</span>
            </div>
        </div>

        <!-- Dock -->
        <div class="dock-container">
            <div class="dock" id="dock"></div>
        </div>
    </div>

    <!-- Window -->
    <div class="window" id="window">
        <div class="win-header">
            <span class="material-symbols-rounded" style="padding:10px; cursor:pointer;" onclick="OS.closeApp()">arrow_back</span>
            <span id="win-title" style="margin-left:10px; font-weight:600;">App</span>
        </div>
        <div class="win-content" id="win-body"></div>
    </div>

    <!-- Control Center -->
    <div class="shade" id="shade">
        <div class="cc-panel">
            <div class="cc-slider">
                <span class="material-symbols-rounded" style="z-index:3; margin-right:12px;">brightness_5</span>
                <div class="slider-fill" id="bright-fill" style="width:100%"></div>
                <input type="range" min="30" max="100" value="100" oninput="OS.setBrightness(this.value)">
            </div>
            <div class="cc-slider">
                <span class="material-symbols-rounded" style="z-index:3; margin-right:12px;">display_settings</span>
                <div class="slider-fill" id="scale-fill" style="width:50%"></div>
                <input type="range" min="80" max="120" value="100" oninput="OS.setScale(this.value)">
            </div>

            <div class="cc-grid" id="cc-grid"></div>

            <div style="text-align:center;">
                <button class="btn" style="background:var(--surface-variant); color:var(--on-surface)" onclick="OS.openCCEditor()">
                    Edit Controls
                </button>
            </div>
        </div>
    </div>

    <!-- Add App Modal -->
    <div class="modal-overlay" id="modal-add-app">
        <div class="modal">
            <h3 style="margin-top:0">Add Shortcut</h3>
            <input type="text" id="new-app-name" placeholder="Name">
            <input type="text" id="new-app-url" placeholder="google.com">
            <label style="font-size:12px; display:block; margin-bottom:5px; opacity:0.7;">Icon (Optional)</label>
            <input type="file" id="new-app-icon" accept="image/*">
            <div style="text-align:right; gap:10px; display:flex; justify-content:flex-end; margin-top:10px;">
                <button onclick="document.getElementById('modal-add-app').classList.remove('open')" style="background:none; border:none; cursor:pointer; color:var(--on-surface)">Cancel</button>
                <button class="btn" onclick="OS.saveNewApp()">Add</button>
            </div>
        </div>
    </div>

    <!-- CC Editor Modal -->
    <div class="modal-overlay" id="modal-cc-edit">
        <div class="modal">
            <h3 style="margin-top:0">Add Tile</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;" id="cc-options"></div>
            <button class="btn" style="width:100%; margin-top:15px;" onclick="document.getElementById('modal-cc-edit').classList.remove('open')">Done</button>
        </div>
    </div>

    <script>
        const SERVER_APPS = <?php echo $serverPayload; ?>;

        const OS = {
            apps: [],
            layout: JSON.parse(localStorage.getItem('px_layout')) || { grid: [], dock: [] },
            userApps: JSON.parse(localStorage.getItem('px_user_apps')) || [],
            ccTiles: JSON.parse(localStorage.getItem('px_cc_tiles')) || [
                {type:'dark'}, {type:'edit'}, {type:'full'}, {type:'sepia'}
            ],
            
            prefs: {
                dark: localStorage.getItem('px_dark') === 'true',
                wall: localStorage.getItem('px_wall') || '',
                bright: localStorage.getItem('px_bright') || 100,
                theme: localStorage.getItem('px_theme') || '#a8c7fa'
            },
            
            tileDefs: [
                {id:'dark', icon:'dark_mode', label:'Dark Mode', action: () => OS.toggleDark()},
                {id:'edit', icon:'edit_square', label:'Edit Home', action: () => OS.toggleEdit(true)},
                {id:'full', icon:'fullscreen', label:'Fullscreen', action: () => OS.toggleFull()},
                {id:'sepia', icon:'nightlight', label:'Night Light', action: () => OS.toggleSepia()},
                {id:'refresh', icon:'refresh', label:'Refresh', action: () => location.reload()},
                {id:'settings', icon:'settings', label:'Settings', action: () => { OS.toggleShade(false); OS.openApp('settings'); }}
            ],
            
            editMode: false,
            isSepia: false,
            activeAppId: null,

            init() {
                this.apps = [...SERVER_APPS, ...this.userApps];
                
                // Reconcile
                const all = this.apps.map(a => a.id);
                const layout = [...this.layout.grid, ...this.layout.dock];
                this.layout.grid.push(...all.filter(id => !layout.includes(id)));
                
                this.renderDesktop();
                this.renderCC();
                this.applyState();
                
                // Events
                document.getElementById('status-bar').onclick = () => this.toggleShade(true);
                document.getElementById('shade').onclick = (e) => { if(e.target.id === 'shade') this.toggleShade(false); };
                
                setInterval(() => document.getElementById('clock').innerText = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}), 1000);
            },

            save() {
                localStorage.setItem('px_layout', JSON.stringify(this.layout));
                localStorage.setItem('px_user_apps', JSON.stringify(this.userApps));
                localStorage.setItem('px_cc_tiles', JSON.stringify(this.ccTiles));
            },

            // --- RENDERING ---
            renderDesktop() {
                const grid = document.getElementById('grid');
                const dock = document.getElementById('dock');
                const addBtn = grid.querySelector('.add-app-btn');
                
                grid.innerHTML = '';
                if(addBtn) grid.appendChild(addBtn);
                dock.innerHTML = '';

                this.layout.grid.forEach(id => {
                    const el = this.buildIcon(id, false);
                    if(el) grid.insertBefore(el, addBtn);
                });

                this.layout.dock.forEach(id => {
                    const el = this.buildIcon(id, true);
                    if(el) dock.appendChild(el);
                });

                if(this.layout.dock.length === 0 && !this.editMode) dock.style.display = 'none';
                else dock.style.display = 'flex';
            },

            buildIcon(id, isDock) {
                const app = this.apps.find(a => a.id === id);
                if(!app) return null;

                const el = document.createElement('div');
                el.className = 'app';
                
                let iconHTML = app.type === 'system' ? `<span class="material-symbols-rounded">settings</span>` : 
                               (app.icon === 'default' ? `<span class="material-symbols-rounded">android</span>` : `<img src="${app.icon}">`);

                el.innerHTML = `
                    <div class="app-icon" id="icon-${id}">${iconHTML}</div>
                    ${!isDock ? `<div class="app-name">${app.name}</div>` : ''}
                    <div class="remove-badge" onclick="event.stopPropagation(); OS.removeApp('${id}')">×</div>
                `;

                let timer;
                el.onpointerdown = () => timer = setTimeout(() => this.toggleEdit(true), 600);
                el.onpointerup = () => { clearTimeout(timer); if(!this.editMode) this.openApp(id); };

                return el;
            },

            renderCC() {
                const grid = document.getElementById('cc-grid');
                grid.innerHTML = '';
                this.ccTiles.forEach((tile, idx) => {
                    const def = this.tileDefs.find(d => d.id === tile.type);
                    if(!def) return;
                    
                    const btn = document.createElement('div');
                    btn.className = 'cc-btn';
                    
                    if(tile.type === 'dark' && this.prefs.dark) btn.classList.add('active');
                    if(tile.type === 'sepia' && this.isSepia) btn.classList.add('active');
                    if(tile.type === 'full' && document.fullscreenElement) btn.classList.add('active');
                    if(tile.type === 'edit' && this.editMode) btn.classList.add('active');

                    btn.innerHTML = `
                        <span class="material-symbols-rounded">${def.icon}</span>
                        <div class="cc-label">${def.label}</div>
                        ${this.editMode ? `<div class="remove-badge" style="display:flex; top:-5px; right:-5px" onclick="event.stopPropagation(); OS.removeCC(${idx})">×</div>` : ''}
                    `;
                    btn.onclick = () => def.action();
                    grid.appendChild(btn);
                });
            },

            // --- ANIMATION SYSTEM ---
            openApp(id) {
                const app = this.apps.find(a => a.id === id);
                
                if (app.type === 'external') {
                    window.open(app.url, '_blank');
                    return;
                }

                this.activeAppId = id;
                const iconEl = document.getElementById(`icon-${id}`);
                const rect = iconEl.getBoundingClientRect();

                // Create ghost
                const clone = document.createElement('div');
                clone.className = 'animator';
                // Copy Style
                clone.style.background = getComputedStyle(iconEl).background;
                clone.style.left = rect.left + 'px'; 
                clone.style.top = rect.top + 'px';
                clone.style.width = rect.width + 'px'; 
                clone.style.height = rect.height + 'px';
                clone.innerHTML = iconEl.innerHTML;
                document.body.appendChild(clone);

                // Animate
                requestAnimationFrame(() => {
                    clone.style.transition = 'all 0.35s cubic-bezier(0.2,0,0,1)';
                    clone.style.left = '0'; clone.style.top = '0'; 
                    clone.style.width = '100vw'; clone.style.height = '100dvh';
                    clone.classList.add('fullscreen');
                    
                    // Fade out content
                    if(clone.querySelector('span')) clone.querySelector('span').style.opacity = '0';
                    if(clone.querySelector('img')) clone.querySelector('img').style.opacity = '0';

                    setTimeout(() => {
                        this.loadWindow(app);
                        document.getElementById('window').classList.add('visible');
                        setTimeout(() => clone.remove(), 400);
                    }, 350);
                });
            },

            closeApp() {
                // Hide window immediately
                document.getElementById('window').classList.remove('visible');
                
                // Reverse Animation Logic
                if (this.activeAppId) {
                    const iconEl = document.getElementById(`icon-${this.activeAppId}`);
                    
                    // If icon exists (app wasn't deleted or moved out of view)
                    if (iconEl) {
                        const rect = iconEl.getBoundingClientRect();
                        
                        const clone = document.createElement('div');
                        clone.className = 'animator fullscreen';
                        clone.style.left = '0'; clone.style.top = '0'; 
                        clone.style.width = '100vw'; clone.style.height = '100dvh';
                        // Mimic window background
                        clone.style.background = 'var(--surface)';
                        document.body.appendChild(clone);

                        requestAnimationFrame(() => {
                            clone.style.transition = 'all 0.35s cubic-bezier(0.2,0,0,1)';
                            clone.style.left = rect.left + 'px';
                            clone.style.top = rect.top + 'px';
                            clone.style.width = rect.width + 'px';
                            clone.style.height = rect.height + 'px';
                            clone.classList.remove('fullscreen');
                            // Match Icon color at end
                            clone.style.background = getComputedStyle(iconEl).background;
                            
                            setTimeout(() => {
                                clone.remove();
                                this.activeAppId = null;
                            }, 350);
                        });
                    }
                }
            },

            loadWindow(app) {
                document.getElementById('win-title').innerText = app.name;
                const body = document.getElementById('win-body');
                body.innerHTML = '';

                if(app.id === 'settings') {
                    body.innerHTML = this.renderSettings();
                } else {
                    const iframe = document.createElement('iframe');
                    iframe.src = app.url;
                    body.appendChild(iframe);
                }
            },

            // --- SETTINGS ---
            renderSettings() {
                return `
                <div class="settings-page">
                    <h2 style="margin-top:0">Settings</h2>
                    
                    <div class="set-group">
                        <div class="set-item">
                            <span>Dark Mode</span>
                            <div class="toggle-switch ${this.prefs.dark?'active':''}" onclick="OS.toggleDark()"></div>
                        </div>
                         <div class="set-item">
                            <span>Theme Color</span>
                            <input type="color" value="${this.prefs.theme}" onchange="OS.setTheme(this.value)">
                        </div>
                    </div>

                    <div class="set-group">
                        <div class="set-item">
                            <span>Wallpaper</span>
                            <label style="background:var(--accent); color:var(--on-accent); padding:5px 12px; border-radius:10px;">
                                Change
                                <input type="file" hidden accept="image/*" onchange="OS.changeWall(this)">
                            </label>
                        </div>
                        <div class="set-item">
                            <span>Reset System</span>
                            <button onclick="localStorage.clear(); location.reload()" style="background:#ffb4ab; color:maroon; border:none; padding:5px 10px; border-radius:8px;">Reset</button>
                        </div>
                    </div>
                </div>`;
            },

            setTheme(hex) {
                this.prefs.theme = hex;
                localStorage.setItem('px_theme', hex);
                document.documentElement.style.setProperty('--accent', hex);
                // Simple contrast check? Assuming black text on color for now
            },

            toggleDark() {
                this.prefs.dark = !this.prefs.dark;
                localStorage.setItem('px_dark', this.prefs.dark);
                document.body.classList.toggle('dark', this.prefs.dark);
                this.renderCC();
                if(document.getElementById('window').classList.contains('visible') && 
                   document.getElementById('win-title').innerText === 'Settings') {
                   document.getElementById('win-body').innerHTML = this.renderSettings(); 
                }
            },

            changeWall(input) {
                if(input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        this.prefs.wall = e.target.result;
                        localStorage.setItem('px_wall', e.target.result);
                        this.applyState();
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            },

            applyState() {
                if(this.prefs.dark) document.body.classList.add('dark');
                if(this.prefs.wall) document.body.style.backgroundImage = `url(${this.prefs.wall})`;
                this.setBrightness(this.prefs.bright);
                this.setTheme(this.prefs.theme);
            },

            setBrightness(val) {
                this.prefs.bright = val;
                localStorage.setItem('px_bright', val);
                document.body.style.filter = `brightness(${val}%) ` + (this.isSepia ? 'sepia(0.6)' : '');
                document.getElementById('bright-fill').style.width = val + '%';
            },

            setScale(val) {
                document.documentElement.style.setProperty('--ui-scale', val/100);
                document.getElementById('scale-fill').style.width = ((val-80)*2.5) + '%';
            },

            toggleSepia() {
                this.isSepia = !this.isSepia;
                this.setBrightness(this.prefs.bright); 
                this.renderCC();
            },
            
            toggleFull() {
                if(!document.fullscreenElement) document.documentElement.requestFullscreen();
                else document.exitFullscreen();
                setTimeout(() => this.renderCC(), 200);
            },

            // --- ADD APP LOGIC ---
            openAddAppModal() {
                document.getElementById('modal-add-app').classList.add('open');
            },

            saveNewApp() {
                const name = document.getElementById('new-app-name').value;
                let url = document.getElementById('new-app-url').value;
                const file = document.getElementById('new-app-icon').files[0];

                if(!name || !url) return alert("Name/URL required");

                // URL Fixer
                if (!/^https?:\/\//i.test(url)) {
                    url = 'https://' + url;
                }

                const finish = (icon) => {
                    const newApp = {
                        id: 'custom_' + Date.now(),
                        name: name,
                        url: url,
                        icon: icon,
                        type: 'external' 
                    };
                    this.userApps.push(newApp);
                    this.apps.push(newApp);
                    this.layout.grid.push(newApp.id);
                    this.save();
                    this.renderDesktop();
                    document.getElementById('modal-add-app').classList.remove('open');
                };

                if(file) {
                    const reader = new FileReader();
                    reader.onload = e => finish(e.target.result);
                    reader.readAsDataURL(file);
                } else {
                    finish('default');
                }
            },

            removeApp(id) {
                if(!confirm("Remove?")) return;
                this.userApps = this.userApps.filter(a => a.id !== id);
                this.layout.grid = this.layout.grid.filter(i => i !== id);
                this.layout.dock = this.layout.dock.filter(i => i !== id);
                this.apps = this.apps.filter(a => a.id !== id);
                this.save();
                this.renderDesktop();
            },

            // --- UI HELPERS ---
            toggleShade(open) {
                const shade = document.getElementById('shade');
                if(open) shade.classList.add('open'); else shade.classList.remove('open');
            },
            
            toggleEdit(force) {
                this.editMode = force;
                document.body.classList.toggle('edit-mode', force);
                this.renderDesktop();
                this.renderCC();
                if(force) this.toggleShade(false);
            },

            openCCEditor() {
                this.toggleShade(false);
                const cont = document.getElementById('cc-options');
                cont.innerHTML = '';
                this.tileDefs.forEach(def => {
                    const btn = document.createElement('div');
                    btn.className = 'cc-btn';
                    btn.innerHTML = `<span class="material-symbols-rounded">${def.icon}</span><div class="cc-label">${def.label}</div>`;
                    btn.onclick = () => {
                        this.ccTiles.push({type: def.id});
                        this.save();
                        this.renderCC();
                        document.getElementById('modal-cc-edit').classList.remove('open');
                        this.toggleShade(true);
                    };
                    cont.appendChild(btn);
                });
                document.getElementById('modal-cc-edit').classList.add('open');
            },

            removeCC(idx) {
                this.ccTiles.splice(idx, 1);
                this.save();
                this.renderCC();
            }
        };

        OS.init();
    </script>
</body>
</html>
