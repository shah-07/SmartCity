/* ============================================================================
   SMART CITY — PAGE AUTH GUARD
   ----------------------------------------------------------------------------
   Every data page loads this before its own script. It does two things:

     1. Sends anyone without a session straight back to the login form, so a
        page is never drawn with an empty or assumed role.
     2. Re-reads the role from the server and corrects sessionStorage if it
        disagrees.

   This is a convenience, not a security boundary. sessionStorage is editable
   from the console, so nothing here can be trusted to keep a citizen out of
   an admin action — that decision belongs to the API, which reads the role
   from the PHP session. What this guard prevents is the page *rendering* the
   wrong controls.

   Pages read the role via SmartCity.userRole rather than defaulting to
   'admin' when the value is missing, which is what previously handed a full
   admin interface to anyone who opened a page directly.
   ========================================================================== */
(function () {
  var LOGIN_PAGE = 'index.html';

  /* --------------------------------------------------------------------
     Escaping helpers
     --------------------------------------------------------------------
     The tables are built by string concatenation from database values, so
     any text a user typed — an incident description, a street name, a
     citizen's name — is dropped straight into markup. Without escaping, a
     value containing an apostrophe ends an attribute early and the rest of
     the row stops working; one containing a tag runs as markup.
     ------------------------------------------------------------------ */

  var HTML_ENTITIES = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  };

  /** Escapes a value for use as element text or inside an attribute. */
  function escapeHTML(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, function (ch) {
      return HTML_ENTITIES[ch];
    });
  }

  /**
   * Serialises an object for an inline handler, e.g. onclick='edit(${...})'.
   * The entities are decoded by the parser before the handler is compiled,
   * so the function still receives ordinary JSON.
   */
  function attrJSON(obj) {
    return escapeHTML(JSON.stringify(obj));
  }

  function toLogin() {
    sessionStorage.clear();
    window.location.replace(LOGIN_PAGE);
  }

  /**
   * Ends the session on the server as well as in the tab.
   *
   * The sidebar links only cleared sessionStorage, which hides the UI but
   * leaves the PHP session alive — the cookie stayed valid and every API
   * endpoint would still have answered it. logout.php clears the session and
   * expires the cookie.
   */
  function logout(event) {
    if (event) event.preventDefault();

    // Navigate whether or not the request succeeds; the local state is gone
    // either way, and a still-live cookie is caught on the next page load.
    fetch('logout.php', { method: 'POST' })
      .catch(function () { /* fall through to the redirect */ })
      .then(function () {
        sessionStorage.clear();
        window.location.href = LOGIN_PAGE;
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-logout]').forEach(function (link) {
      link.addEventListener('click', logout);
    });
  });

  var storedRole = sessionStorage.getItem('userRole');

  window.SmartCity = {
    userRole: storedRole,
    isAdmin: storedRole === 'admin',
    isCitizen: storedRole !== 'admin',
    escapeHTML: escapeHTML,
    attrJSON: attrJSON
  };

  // No stored role means the user never completed a login in this tab.
  if (!storedRole) {
    toLogin();
    return;
  }

  // Confirm against the server session. The page keeps rendering meanwhile;
  // if the session is gone the redirect happens a moment later, and the API
  // would refuse the requests in any case.
  fetch('auth_check.php')
    .then(function (response) {
      if (!response.ok) {
        toLogin();
        return null;
      }
      return response.json();
    })
    .then(function (data) {
      if (!data) return;

      if (!data.authenticated) {
        toLogin();
        return;
      }

      // The stored role was edited, or the account changed since login.
      // Reload once with the true value; after the reload the two agree, so
      // this cannot loop.
      if (data.role !== storedRole) {
        sessionStorage.setItem('userRole', data.role);
        sessionStorage.setItem('username', data.username);
        window.location.reload();
      }
    })
    .catch(function () {
      // A network failure is not proof of logout, so the page is left alone;
      // every API call it makes still has to pass the server-side check.
    });
})();
