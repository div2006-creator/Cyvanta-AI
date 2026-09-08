const { app, BrowserWindow, shell } = require('electron');
const { spawn, execSync } = require('child_process');
const path = require('path');
const http = require('http');

let phpProcess = null;
let wsProcess = null;
let mainWindow = null;

function startPhpBackend(callback) {
  const phpCmd = 'php';
  const phpArgs = [
    '-d', 'upload_max_filesize=100M',
    '-d', 'post_max_size=100M',
    '-d', 'memory_limit=256M',
    '-S', '127.0.0.1:8000',
    '-t', path.join(__dirname, 'public')
  ];

  phpProcess = spawn(phpCmd, phpArgs, { cwd: __dirname });

  phpProcess.stdout.on('data', (data) => console.log(`[PHP] ${data}`));
  phpProcess.stderr.on('data', (data) => console.log(`[PHP Log] ${data}`));

  try {
    execSync('php database/seed.php', { cwd: __dirname });
  } catch (e) {
    console.error('Seeding notice:', e.message);
  }

  try {
    wsProcess = spawn('php', ['websocket/server.php'], { cwd: __dirname });
  } catch (e) {
    console.error('WebSocket server notice:', e.message);
  }

  let retries = 0;
  const checkServer = () => {
    http.get('http://127.0.0.1:8000', () => {
      callback();
    }).on('error', () => {
      retries++;
      if (retries < 25) {
        setTimeout(checkServer, 200);
      } else {
        callback();
      }
    });
  };
  checkServer();
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1440,
    height: 900,
    minWidth: 1024,
    minHeight: 700,
    title: 'CYVANTA — AI Criminal Network Analysis',
    icon: path.join(__dirname, 'public/assets/images/favicon.svg'),
    autoHideMenuBar: true,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
    }
  });

  mainWindow.loadURL('http://127.0.0.1:8000');

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith('http://127.0.0.1:8000')) {
      return { action: 'allow' };
    }
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

app.on('ready', () => {
  startPhpBackend(() => {
    createWindow();
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('will-quit', () => {
  if (phpProcess) phpProcess.kill();
  if (wsProcess) wsProcess.kill();
});
