self.addEventListener("install", function () {
  self.skipWaiting();
});

self.addEventListener("activate", function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener("notificationclick", function (event) {
  event.notification.close();

  var targetUrl = self.location.origin + "/";
  if (event.notification && event.notification.data && typeof event.notification.data.url === "string" && event.notification.data.url.trim() !== "") {
    try {
      var parsedUrl = new URL(event.notification.data.url, self.location.origin);
      if (parsedUrl.origin === self.location.origin) {
        targetUrl = parsedUrl.href;
      }
    } catch (error) {
      targetUrl = self.location.origin + "/";
    }
  }

  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (clients) {
      var matchedClient = null;

      clients.some(function (client) {
        if (!client || !client.url) {
          return false;
        }

        var currentUrl = client.url.split("#")[0];
        var desiredUrl = targetUrl.split("#")[0];
        if (currentUrl === desiredUrl) {
          matchedClient = client;
          return true;
        }

        return false;
      });

      if (matchedClient) {
        if (typeof matchedClient.focus === "function") {
          return matchedClient.focus();
        }
        return matchedClient;
      }

      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }

      return null;
    })
  );
});
