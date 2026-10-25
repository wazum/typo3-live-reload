# Content Security Policy

The extension handles nonces for you:

- Both injected script elements and the `csp-nonce` meta element — which Vite's own client
  reads — get TYPO3's nonce for the request.
- The extension adds `'nonce-proxy'` to `script-src` and `script-src-elem` in its active
  contexts. The nonce then also appears in the CSP header, even on sites whose own policy
  defines `script-src-elem` without a nonce.
- On cacheable pages, TYPO3 normally drops the nonce from the header when nothing used it
  during rendering. The extension declares that it uses the nonce, so the header keeps it
  whenever the scripts are injected.

One thing is left for you when your development CSP is strict and doesn't use
`'strict-dynamic'`: allow the dev server in `connect-src` for the HMR WebSocket. Use the
`ws://` or `wss://` form of the dev server origin, matching your protocol.
