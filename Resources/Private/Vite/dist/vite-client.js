// src/core.ts
var scrollStorageKey = "live-reload:scroll";
function restoreScrollPosition() {
  try {
    const storedScroll = sessionStorage.getItem(scrollStorageKey);
    if (!storedScroll) return;
    sessionStorage.removeItem(scrollStorageKey);
    const position = JSON.parse(storedScroll);
    if (position.href !== window.location.href) return;
    const restore = () => {
      if (history.scrollRestoration === "manual") window.scrollTo(position.x, position.y);
    };
    if (document.readyState === "complete") restore();
    else window.addEventListener("load", restore, { once: true });
  } catch {
  }
}
function createClientCore(configuration2) {
  let missedWhilePaused = false;
  const announceConnection = (connected) => {
    configuration2.connection = { connected, mode: configuration2.mode };
    document.dispatchEvent(
      new CustomEvent("typo3:live-reload:connection", {
        detail: { connected, mode: configuration2.mode }
      })
    );
  };
  const storeScrollPosition = () => {
    try {
      sessionStorage.setItem(
        scrollStorageKey,
        JSON.stringify({ x: window.scrollX, y: window.scrollY, href: window.location.href })
      );
    } catch {
    }
  };
  const handleBroadcast = (payload) => {
    const received = payload;
    const broadcastTags = Array.isArray(received?.tags) ? received.tags : [];
    const ownTags = Array.isArray(configuration2.tags) ? configuration2.tags : [];
    const affected = configuration2.mode === "always" || ownTags.length === 0 || broadcastTags.some((tag) => ownTags.includes(tag));
    document.dispatchEvent(
      new CustomEvent("typo3:live-reload:broadcast", {
        detail: { tags: broadcastTags, matched: affected, mode: configuration2.mode }
      })
    );
    if (affected && configuration2.mode === "paused") missedWhilePaused = true;
    if (!affected || configuration2.mode === "paused") return;
    const notice = new CustomEvent("typo3:live-reload", { cancelable: true, detail: { tags: broadcastTags } });
    if (!document.dispatchEvent(notice)) return;
    storeScrollPosition();
    window.location.reload();
  };
  const forceReload = () => {
    if (configuration2.mode === "paused") {
      missedWhilePaused = true;
      return;
    }
    storeScrollPosition();
    window.location.reload();
  };
  if (typeof BroadcastChannel !== "undefined") {
    new BroadcastChannel("live-reload").addEventListener("message", (event) => {
      const data = event.data;
      const mode = data && data.mode;
      if (mode !== "tagged" && mode !== "always" && mode !== "paused") {
        window.location.reload();
        return;
      }
      if (mode !== "paused" && missedWhilePaused) {
        window.location.reload();
        return;
      }
      configuration2.mode = mode;
      announceConnection(configuration2.connection ? configuration2.connection.connected : true);
    });
  }
  return { announceConnection, handleBroadcast, forceReload };
}

// src/vite-client.ts
restoreScrollPosition();
var configuration = window.__liveReload;
if (configuration && import.meta.hot) {
  const core = createClientCore(configuration);
  core.announceConnection(true);
  import.meta.hot.on("vite:ws:disconnect", () => core.announceConnection(false));
  import.meta.hot.on("vite:ws:connect", () => core.announceConnection(true));
  import.meta.hot.on("typo3:live-reload", (payload) => core.handleBroadcast(payload));
}
