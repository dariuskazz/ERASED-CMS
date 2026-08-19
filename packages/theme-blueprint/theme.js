(function(){
  "use strict";
  var body = document.body;
  if (!body || !body.classList.contains("theme-dark-green")) return;

  var STORAGE_KEY = "erased-blueprint-theme";

  function systemPrefersLight(){
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches;
  }

  function apply(mode, toggleBtn){
    body.setAttribute("data-blueprint-theme", mode);
    if (toggleBtn) {
      toggleBtn.setAttribute("aria-label", mode === "dark" ? "Switch to light mode" : "Switch to dark mode");
      toggleBtn.setAttribute("aria-pressed", mode === "light" ? "true" : "false");
    }
    try { localStorage.setItem(STORAGE_KEY, mode); } catch (e) {}
  }

  var nav = document.querySelector(".public-header .public-nav") || document.querySelector("nav");
  if (!nav) return;

  var toggle = document.createElement("button");
  toggle.type = "button";
  toggle.className = "blueprint-theme-toggle";
  toggle.innerHTML =
    '<svg class="bp-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>' +
    '<svg class="bp-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z"/></svg>';
  nav.appendChild(toggle);

  var saved = null;
  try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
  apply(saved === "light" || saved === "dark" ? saved : (systemPrefersLight() ? "light" : "dark"), toggle);

  toggle.addEventListener("click", function(){
    apply(body.getAttribute("data-blueprint-theme") === "dark" ? "light" : "dark", toggle);
  });
})();
