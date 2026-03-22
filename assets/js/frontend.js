(function () {
  function normalizeSlug(value) {
    return String(value || "").toLowerCase().trim();
  }

  function toList(value) {
    if (Array.isArray(value)) {
      return value;
    }
    if (!value || typeof value !== "object") {
      return [];
    }
    var keys = Object.keys(value);
    if (!keys.length) {
      return [];
    }
    keys.sort(function (a, b) {
      var ai = parseInt(a, 10);
      var bi = parseInt(b, 10);
      if (isNaN(ai) || isNaN(bi)) {
        return String(a).localeCompare(String(b));
      }
      return ai - bi;
    });
    return keys
      .map(function (key) {
        return value[key];
      })
      .filter(Boolean);
  }

  function esc(value) {
    var raw = value === null || value === undefined ? "" : value;
    return String(raw).replace(/[&<>"']/g, function (ch) {
      if (ch === "&") {
        return "&amp;";
      }
      if (ch === "<") {
        return "&lt;";
      }
      if (ch === ">") {
        return "&gt;";
      }
      if (ch === '"') {
        return "&quot;";
      }
      return "&#39;";
    });
  }

  function icon(kind, url, label) {
    return (
      '<a class="opentt-matches-list-icon opentt-matches-list-icon--' +
      kind +
      '" href="' +
      esc(url) +
      '" aria-label="' +
      esc(label) +
      '" title="' +
      esc(label) +
      '">' +
      esc(kind === "report" ? "R" : "V") +
      "</a>"
    );
  }

  function rowHtml(match, i18n) {
    var icons = "";
    if (match.reportUrl) {
      icons += icon("report", match.reportUrl, i18n.reportLabel || "Izveštaj");
    }
    if (match.videoUrl) {
      icons += icon("video", match.videoUrl, i18n.videoLabel || "Snimak");
    }

    return (
      '<div class="opentt-matches-list-row ' +
      esc(match.rowClass || "") +
      '" data-link="' +
      esc(match.link || "#") +
      '" tabindex="0" role="link">' +
      '<div class="opentt-matches-list-col opentt-matches-list-col--date">' +
      esc(match.date) +
      "</div>" +
      '<div class="opentt-matches-list-col opentt-matches-list-col--match">' +
      '<span class="match-side match-side--home">' +
      '<span class="team-name team-name--home ' +
      esc(match.homeClass || "") +
      '">' +
      esc(match.homeName) +
      "</span>" +
      '<span class="team-crest">' +
      (match.homeLogo || "") +
      "</span>" +
      "</span>" +
      '<span class="match-score ' +
      (match.showTime ? "is-time" : "") +
      '">' +
      (match.showTime
        ? '<span class="match-time">' + esc(match.timeLabel || "--:--") + "</span>"
        : '<span class="team-score ' +
          esc(match.homeClass || "") +
          '">' +
          esc(match.homeScore) +
          '</span><span class="team-sep">:</span><span class="team-score ' +
          esc(match.awayClass || "") +
          '">' +
          esc(match.awayScore) +
          "</span>") +
      "</span>" +
      '<span class="match-side match-side--away">' +
      '<span class="team-crest">' +
      (match.awayLogo || "") +
      "</span>" +
      '<span class="team-name team-name--away ' +
      esc(match.awayClass || "") +
      '">' +
      esc(match.awayName) +
      "</span>" +
      "</span>" +
      "</div>" +
      '<div class="opentt-matches-list-col opentt-matches-list-col--media">' +
      icons +
      "</div>" +
      "</div>"
    );
  }

  function initMatchesList(root) {
    if (!root || root.dataset.openttListReady === "1") {
      return;
    }

    var dataNode = root.querySelector(".opentt-matches-list-data");
    if (!dataNode) {
      return;
    }

    var rawData = dataNode.textContent || dataNode.value || "";
    var data = null;
    try {
      data = JSON.parse(rawData);
    } catch (err) {
      return;
    }
    if (!data || !Array.isArray(data.rounds) || !data.rounds.length) {
      return;
    }

    var navPrev = root.querySelector(".opentt-matches-list-nav-btn.is-prev");
    var navNext = root.querySelector(".opentt-matches-list-nav-btn.is-next");
    var roundLabel = root.querySelector(".opentt-matches-list-round");
    var body = root.querySelector(".opentt-matches-list-body");
    if (!navPrev || !navNext || !roundLabel || !body) {
      return;
    }

    root.dataset.openttListReady = "1";

    var rounds = data.rounds;
    var i18n = data.i18n || {};
    var matchesByRound = data.matchesByRound || {};
    var roundLists = Array.isArray(data.roundLists) ? data.roundLists : [];
    var roundHtmlByIndex = Array.isArray(data.roundHtmlByIndex)
      ? data.roundHtmlByIndex
      : [];
    var normalizedRoundKeys = {};

    Object.keys(matchesByRound).forEach(function (key) {
      normalizedRoundKeys[normalizeSlug(key)] = key;
    });

    var roundIndex = parseInt(data.defaultRoundIndex || "0", 10);
    if (isNaN(roundIndex) || roundIndex < 0 || roundIndex >= rounds.length) {
      roundIndex = 0;
      for (var i = 0; i < rounds.length; i++) {
        if ((rounds[i].slug || "") === (data.defaultRound || "")) {
          roundIndex = i;
          break;
        }
      }
    }

    (function ensureInitialRoundHasContent() {
      if (!rounds.length) {
        return;
      }
      var hasCurrent =
        String(roundHtmlByIndex[roundIndex] || "") !== "" ||
        toList(roundLists[roundIndex]).length > 0;
      if (hasCurrent) {
        return;
      }
      for (var pos = rounds.length - 1; pos >= 0; pos--) {
        var ok =
          String(roundHtmlByIndex[pos] || "") !== "" ||
          toList(roundLists[pos]).length > 0;
        if (ok) {
          roundIndex = pos;
          return;
        }
      }
    })();

    function resolveRoundList(roundSlug) {
      var direct = toList(matchesByRound[roundSlug]);
      if (direct.length) {
        return direct;
      }

      var normalizedKey = normalizedRoundKeys[normalizeSlug(roundSlug)] || "";
      if (normalizedKey) {
        var normalizedList = toList(matchesByRound[normalizedKey]);
        if (normalizedList.length) {
          return normalizedList;
        }
      }

      return [];
    }

    function render() {
      var current = rounds[roundIndex] || null;
      if (!current) {
        body.innerHTML = "<p>" + esc(i18n.noMatches || "Nema utakmica.") + "</p>";
        return;
      }

      roundLabel.textContent = current.name || current.slug || "";
      navPrev.disabled = roundIndex <= 0;
      navNext.disabled = roundIndex >= rounds.length - 1;
      navPrev.classList.toggle("is-disabled", !!navPrev.disabled);
      navNext.classList.toggle("is-disabled", !!navNext.disabled);

      var htmlByIndex = String(roundHtmlByIndex[roundIndex] || "");
      if (htmlByIndex) {
        body.innerHTML = htmlByIndex;
        return;
      }

      var list = toList(roundLists[roundIndex]);
      if (!list.length) {
        list = resolveRoundList(current.slug || "");
      }
      if (!list.length) {
        body.innerHTML = "<p>" + esc(i18n.noMatches || "Nema utakmica.") + "</p>";
        return;
      }

      var html = '<div class="opentt-matches-list-items">';
      for (var idx = 0; idx < list.length; idx++) {
        html += rowHtml(list[idx], i18n);
      }
      html += "</div>";
      body.innerHTML = html;
    }

    function syncNavState() {
      var current = rounds[roundIndex] || null;
      if (current) {
        roundLabel.textContent = current.name || current.slug || "";
      }
      navPrev.disabled = roundIndex <= 0;
      navNext.disabled = roundIndex >= rounds.length - 1;
      navPrev.classList.toggle("is-disabled", !!navPrev.disabled);
      navNext.classList.toggle("is-disabled", !!navNext.disabled);
    }

    function stepRound(direction) {
      var dir = parseInt(direction, 10);
      if (isNaN(dir) || dir === 0) {
        return false;
      }
      if (dir < 0 && roundIndex > 0) {
        roundIndex -= 1;
        render();
        return true;
      }
      if (dir > 0 && roundIndex < rounds.length - 1) {
        roundIndex += 1;
        render();
        return true;
      }
      return false;
    }

    root.addEventListener("click", function (e) {
      var nav =
        e.target && e.target.closest
          ? e.target.closest(".opentt-matches-list-nav-btn[data-direction]")
          : null;
      if (nav && root.contains(nav)) {
        e.preventDefault();
        if (nav.classList && nav.classList.contains("is-disabled")) {
          return;
        }
        var navDir = parseInt(nav.getAttribute("data-direction") || "0", 10);
        stepRound(navDir);
        return;
      }

      var iconEl =
        e.target && e.target.closest
          ? e.target.closest(".opentt-matches-list-icon")
          : null;
      if (iconEl) {
        e.stopPropagation();
        return;
      }

      var row =
        e.target && e.target.closest
          ? e.target.closest(".opentt-matches-list-row")
          : null;
      if (!row) {
        return;
      }
      var link = row.getAttribute("data-link") || "";
      if (link) {
        window.location.href = link;
      }
    });

    root.addEventListener("keydown", function (e) {
      if (e.key !== "Enter" && e.key !== " ") {
        return;
      }
      var row =
        e.target && e.target.closest
          ? e.target.closest(".opentt-matches-list-row")
          : null;
      if (!row) {
        return;
      }
      e.preventDefault();
      var link = row.getAttribute("data-link") || "";
      if (link) {
        window.location.href = link;
      }
    });

    // Keep server-rendered initial HTML untouched to avoid late reflow/style
    // regressions on themes that post-process content after load.
    syncNavState();
  }

  function parseJsonNode(node) {
    if (!node) {
      return null;
    }
    var raw = node.textContent || node.value || "";
    if (!raw) {
      return null;
    }
    try {
      return JSON.parse(raw);
    } catch (err) {
      return null;
    }
  }

  function debounce(fn, wait) {
    var timer = null;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(null, args);
      }, wait);
    };
  }

  function renderSearchGroups(container, groups) {
    if (!container) {
      return;
    }
    if (!Array.isArray(groups) || !groups.length) {
      container.innerHTML = '<p class="opentt-search-empty">Nema rezultata.</p>';
      return;
    }

    var html = "";
    groups.forEach(function (group) {
      var label = esc(group && group.label ? group.label : "");
      var items = Array.isArray(group && group.items) ? group.items : [];
      if (!items.length) {
        return;
      }
      html += '<section class="opentt-search-group">';
      html += '<h4 class="opentt-search-group-title">' + label + "</h4>";
      html += '<div class="opentt-search-group-items">';
      items.forEach(function (item) {
        var title = esc(item && item.title ? item.title : "");
        var url = esc(item && item.url ? item.url : "#");
        var meta = esc(item && item.meta ? item.meta : "");
        html += '<a class="opentt-search-item" href="' + url + '">';
        html += '<span class="opentt-search-item-title">' + title + "</span>";
        if (meta) {
          html += '<span class="opentt-search-item-meta">' + meta + "</span>";
        }
        html += "</a>";
      });
      html += "</div></section>";
    });
    container.innerHTML = html || '<p class="opentt-search-empty">Nema rezultata.</p>';
  }

  function initSearch(root) {
    if (!root || root.dataset.openttSearchReady === "1") {
      return;
    }
    var data = parseJsonNode(root.querySelector(".opentt-search-data")) || {};
    var toggle = root.querySelector(".opentt-search-toggle");
    var panel = root.querySelector(".opentt-search-panel");
    var input = root.querySelector(".opentt-search-input");
    var results = root.querySelector("[data-opentt-search-results]");
    if (!toggle || !panel || !input || !results) {
      return;
    }

    root.dataset.openttSearchReady = "1";
    var minChars = parseInt(data.minChars || 1, 10);
    if (isNaN(minChars) || minChars < 1) {
      minChars = 1;
    }
    var limit = parseInt(data.limit || 6, 10);
    if (isNaN(limit) || limit < 1) {
      limit = 6;
    }
    var context = data.context && typeof data.context === "object" ? data.context : {};
    var i18n = data.i18n && typeof data.i18n === "object" ? data.i18n : {};
    var promptText =
      i18n.prompt || ("Unesi najmanje " + String(minChars) + " karakter(a).");
    var loadingText = i18n.loading || "Pretraga...";
    var emptyText = i18n.empty || "Nema rezultata.";
    var currentController = null;

    function closePanel() {
      panel.hidden = true;
      toggle.setAttribute("aria-expanded", "false");
    }

    function openPanel() {
      panel.hidden = false;
      toggle.setAttribute("aria-expanded", "true");
      setTimeout(function () {
        input.focus();
      }, 0);
    }

    toggle.addEventListener("click", function () {
      if (panel.hidden) {
        openPanel();
      } else {
        closePanel();
      }
    });

    document.addEventListener("click", function (e) {
      if (!root.contains(e.target)) {
        closePanel();
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !panel.hidden) {
        closePanel();
      }
    });

    var runSearch = debounce(function (value) {
      var query = String(value || "").trim();
      if (query.length < minChars) {
        results.innerHTML = '<p class="opentt-search-empty">' + esc(promptText) + "</p>";
        return;
      }

      var ajaxUrl =
        window.openttFrontend && window.openttFrontend.ajaxUrl
          ? String(window.openttFrontend.ajaxUrl)
          : "";
      if (!ajaxUrl) {
        results.innerHTML = '<p class="opentt-search-empty">' + esc(emptyText) + "</p>";
        return;
      }

      if (currentController && typeof currentController.abort === "function") {
        currentController.abort();
      }
      currentController =
        typeof AbortController !== "undefined" ? new AbortController() : null;

      results.innerHTML = '<p class="opentt-search-loading">' + esc(loadingText) + "</p>";

      var body = new URLSearchParams();
      body.set("action", "opentt_frontend_search");
      body.set("nonce", String(window.openttFrontend.searchNonce || ""));
      body.set("q", query);
      body.set("limit", String(limit));
      body.set("context", JSON.stringify(context));

      fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: body.toString(),
        signal: currentController ? currentController.signal : undefined,
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (payload) {
          if (!payload || payload.success !== true) {
            results.innerHTML = '<p class="opentt-search-empty">' + esc(emptyText) + "</p>";
            return;
          }
          var data = payload.data && typeof payload.data === "object" ? payload.data : {};
          renderSearchGroups(results, data.groups || []);
        })
        .catch(function () {
          results.innerHTML = '<p class="opentt-search-empty">' + esc(emptyText) + "</p>";
        });
    }, 170);

    input.addEventListener("input", function () {
      runSearch(input.value || "");
    });
  }

  function initAllMatchesLists() {
    var roots = document.querySelectorAll('[data-opentt-matches-list="1"]');
    for (var i = 0; i < roots.length; i++) {
      initMatchesList(roots[i]);
    }
  }

  function initAllSearches() {
    var roots = document.querySelectorAll('[data-opentt-search="1"]');
    for (var i = 0; i < roots.length; i++) {
      initSearch(roots[i]);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initAllMatchesLists();
      initAllSearches();
    });
  } else {
    initAllMatchesLists();
    initAllSearches();
  }
})();
