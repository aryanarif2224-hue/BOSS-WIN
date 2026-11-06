/**
 * Frontend license gate.
 * The SPA only runs when the installation is licensed. This runs before the
 * app boots, re-checks the license with the server, and hard-stops the app if
 * the license is missing, expired, revoked or for a different domain.
 */
(function () {
  'use strict';
  var LIC = window.__LIC__ || null;
  var API = (window.__LIC_API__ || '/api/license') + '/verify';

  function stop(msg) {
    try {
      document.documentElement.innerHTML =
        '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
        '<title>License required</title>' +
        '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;' +
        'font-family:Inter,system-ui,sans-serif;background:#0a0e17;color:#e7ecf5;padding:20px}' +
        '.b{width:100%;max-width:420px;background:#161d2c;border:1px solid #273049;border-radius:22px;padding:28px}' +
        'h1{font-size:20px;margin:0 0 8px}p{color:#8b97b3;font-size:14px;line-height:1.6;margin:0}' +
        'code{background:#0d1320;padding:2px 6px;border-radius:6px;color:#fbbf24}</style></head><body>' +
        '<div class="b"><h1>License required</h1><p>' + msg + '</p>' +
        '<p style="margin-top:14px">Open <code>' + location.origin + '/license.php</code> and enter your activation code.</p></div></body>';
    } catch (e) {}
    throw new Error('license required');
  }

  if (!LIC) stop('This installation is not activated.');

  // The page shell is only served when the backend already validated the
  // license, so a missing/invalid marker means something was tampered with.
  if (LIC.status !== 'active') stop('This installation is not activated.');

  // Re-verify against the license server in the background. If it comes back
  // invalid (revoked / wrong domain) the app is stopped right away.
  function recheck() {
    try {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', API, true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        if (xhr.status !== 200) return;
        try {
          var r = JSON.parse(xhr.responseText);
          if (r && r.ok === false) stop('Your license is no longer valid.');
          if (r && r.data && r.data.valid === false) stop('Your license is no longer valid.');
        } catch (e) {}
      };
      xhr.send(JSON.stringify({
        code: LIC.code, domain: LIC.domain, fingerprint: LIC.fp || ''
      }));
    } catch (e) {}
  }

  recheck();
  setInterval(recheck, 6 * 60 * 60 * 1000);
})();
