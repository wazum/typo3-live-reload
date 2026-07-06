# Demo Recording

`record.mjs` records the README demo: the TYPO3 backend saves a content element, the frontend tab that shows this record reloads, a second tab stays untouched. Both frontend tabs show the admin panel bar with the Live Reload status.

The browsers run in a Playwright container that reaches the fixture app at `http://web:8080`. Bring the harness up and adjust it once:

1. `bash Tests/E2E/run.sh`
2. In `.app/vite.config.ts`: set `host: '0.0.0.0'`, add `allowedHosts: true` and `cors: true`, restart Vite.
3. Restart the PHP server bound to `0.0.0.0:8080` with `-t public`, with `VITE_SERVER_URI` unset and `VITE_PRIMARY_PORT=5273` set.
4. Add a base variant for `http://web:8080/` to `.app/config/sites/main/config.yaml`.
5. Create a backend user `demo` (password `DemoDemo1!`, admin) for the recording. Give it this TSconfig, so the admin panel bar only shows the Live Reload module:

   ```
   admPanel.enable.all = 0
   admPanel.enable.live_reload = 1
   ```

6. The admin panel needs `typo3/cms-adminpanel` installed and `config.admPanel = 1` in the TypoScript. Note: the fixture seeds a root `sys_template` record that clears the site set TypoScript, so append the line to the `sys_template` `config` field, not to the set.
7. Flush caches.

Record (the script logs in as `demo`, opens the admin panel once, then records three synchronized contexts — the save happens as a real backend form submit):

```bash
# playwright container
PLAYWRIGHT_BROWSERS_PATH=/ms-playwright node demo/record.mjs
```

Reset between takes:

```bash
sqlite3 .app/var/sqlite/*.sqlite 'update tt_content set header="Home content" where uid=7;'
```

Compose the GIF from the three recordings (backend on top, then the two frontend tabs; `-ss 4` skips the page loads):

```bash
ffmpeg -ss 4 -t 10 -i backend.webm -ss 4 -t 10 -i top.webm -ss 4 -t 10 -i bottom.webm -filter_complex \
  "[0:v]fps=10,scale=840:-2[a];[1:v]fps=10,scale=840:-2[b];[2:v]fps=10,scale=840:-2[c];[a][b][c]vstack=inputs=3,split[s0][s1];[s0]palettegen=stats_mode=diff[p];[s1][p]paletteuse=dither=bayer:bayer_scale=5" \
  ../../Documentation/demo.gif
```
