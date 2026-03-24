document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.getElementById("togglePassword");
  const pwd = document.getElementById("passwordInput");
  if (!toggle || !pwd) return;

  const eyeSVG =
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
  const eyeOffSVG =
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path><path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

  const icon = toggle.querySelector("i");
  if (icon && !icon.innerHTML.trim()) {
    icon.innerHTML = eyeSVG;
    toggle.setAttribute("title", "Show password");
    toggle.setAttribute("aria-label", "Show password");
  }

  toggle.addEventListener("click", () => {
    const wasPassword = pwd.type === "password";
    pwd.type = wasPassword ? "text" : "password";
    const currentIcon = toggle.querySelector("i");
    if (currentIcon) {
      currentIcon.innerHTML = wasPassword ? eyeOffSVG : eyeSVG;
      toggle.setAttribute("title", wasPassword ? "Hide password" : "Show password");
      toggle.setAttribute("aria-label", wasPassword ? "Hide password" : "Show password");
    }
  });
});
