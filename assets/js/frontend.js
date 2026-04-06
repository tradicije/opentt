(function () {
  var openttScrollLockCount = 0;
  var openttSavedScrollY = 0;
  var openttActiveSearchPanel = null;
  var openttScrollGuardsAttached = false;
  var openttSearchAssets = {};

  function preventBackgroundScroll(event) {
    if (!openttActiveSearchPanel) {
      return;
    }
    var target = event && event.target ? event.target : null;
    if (target && openttActiveSearchPanel.contains(target)) {
      return;
    }
    if (event && typeof event.preventDefault === "function") {
      event.preventDefault();
    }
  }

  function attachScrollGuards() {
    if (openttScrollGuardsAttached) {
      return;
    }
    openttScrollGuardsAttached = true;
    document.addEventListener("wheel", preventBackgroundScroll, {
      passive: false,
      capture: true,
    });
    document.addEventListener("touchmove", preventBackgroundScroll, {
      passive: false,
      capture: true,
    });
  }

  function detachScrollGuards() {
    if (!openttScrollGuardsAttached) {
      return;
    }
    openttScrollGuardsAttached = false;
    document.removeEventListener("wheel", preventBackgroundScroll, true);
    document.removeEventListener("touchmove", preventBackgroundScroll, true);
  }

  function lockPageScroll(panel) {
    if (openttScrollLockCount === 0) {
      openttSavedScrollY = window.scrollY || window.pageYOffset || 0;
      if (document.documentElement && document.documentElement.classList) {
        document.documentElement.classList.add("opentt-search-open");
      }
      if (document.body && document.body.classList) {
        document.body.classList.add("opentt-search-open");
        document.body.style.position = "fixed";
        document.body.style.top = "-" + String(openttSavedScrollY) + "px";
        document.body.style.left = "0";
        document.body.style.right = "0";
        document.body.style.width = "100%";
      }
      attachScrollGuards();
    }
    if (panel) {
      openttActiveSearchPanel = panel;
    }
    openttScrollLockCount += 1;
  }

  function unlockPageScroll() {
    if (openttScrollLockCount <= 0) {
      return;
    }
    if (openttScrollLockCount > 0) {
      openttScrollLockCount -= 1;
    }
    if (openttScrollLockCount > 0) {
      return;
    }
    openttActiveSearchPanel = null;
    detachScrollGuards();
    if (document.documentElement && document.documentElement.classList) {
      document.documentElement.classList.remove("opentt-search-open");
    }
    if (document.body && document.body.classList) {
      document.body.classList.remove("opentt-search-open");
      document.body.style.position = "";
      document.body.style.top = "";
      document.body.style.left = "";
      document.body.style.right = "";
      document.body.style.width = "";
    }
    window.scrollTo(0, openttSavedScrollY || 0);
  }

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

  function renderSearchGroups(container, groups, highlightQuery) {
    if (!container) {
      return;
    }
    if (!Array.isArray(groups) || !groups.length) {
      if (container.classList) {
        container.classList.remove("opentt-search-has-side-popular");
      }
      container.innerHTML = '<p class="opentt-search-empty">Nema rezultata.</p>';
      return;
    }

    var hasPopularPlayers = false;
    var hasPopularClubs = false;
    var hasTrending = false;
    var hasLatestResults = false;
    groups.forEach(function (group) {
      var key = String(group && group.key ? group.key : "");
      var items = Array.isArray(group && group.items) ? group.items : [];
      if (!items.length) {
        return;
      }
      if (key === "players") {
        hasPopularPlayers = true;
      }
      if (key === "clubs") {
        hasPopularClubs = true;
      }
      if (key === "trending") {
        hasTrending = true;
      }
      if (key === "latest_results") {
        hasLatestResults = true;
      }
    });
    if (container.classList) {
      container.classList.toggle(
        "opentt-search-has-side-popular",
        hasPopularPlayers && hasPopularClubs
      );
      container.classList.toggle(
        "opentt-search-has-side-discovery",
        hasTrending && hasLatestResults && hasPopularPlayers && hasPopularClubs
      );
    }

    function trendingRankHtml(rank) {
      var r = parseInt(rank, 10);
      if (isNaN(r) || r <= 0) {
        return "";
      }
      if (r >= 4) {
        return (
          '<span class="opentt-search-rank-badge opentt-search-rank-badge--num">' +
          esc(String(r)) +
          "</span>"
        );
      }

      var icon = "";
      if (r === 1) {
        icon = String(openttSearchAssets.trendingOneIcon || "");
      } else if (r === 2) {
        icon = String(openttSearchAssets.trendingTwoIcon || "");
      } else if (r === 3) {
        icon = String(openttSearchAssets.trendingThreeIcon || "");
      }
      if (!icon) {
        return (
          '<span class="opentt-search-rank-badge opentt-search-rank-badge--num">' +
          esc(String(r)) +
          "</span>"
        );
      }

      return (
        '<span class="opentt-search-rank-badge opentt-search-rank-badge--icon opentt-search-rank-badge--' +
        esc(String(r)) +
        '" style="--opentt-rank-icon:url(\'' +
        esc(icon) +
        '\')" aria-label="' +
        esc("Top " + String(r)) +
        '"></span>'
      );
    }

    function escapeRegex(value) {
      return String(value || "").replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    }

    function highlightText(value, query) {
      var raw = String(value || "");
      var q = String(query || "").trim();
      if (!q) {
        return esc(raw);
      }
      var re = null;
      try {
        re = new RegExp("(" + escapeRegex(q) + ")", "gi");
      } catch (err) {
        return esc(raw);
      }

      var parts = raw.split(re);
      if (!parts || parts.length <= 1) {
        return esc(raw);
      }

      var htmlParts = "";
      for (var i = 0; i < parts.length; i++) {
        var part = String(parts[i] || "");
        if (!part) {
          continue;
        }
        if (part.toLowerCase() === q.toLowerCase()) {
          htmlParts += '<strong class="opentt-search-hit">' + esc(part) + "</strong>";
        } else {
          htmlParts += esc(part);
        }
      }
      return htmlParts || esc(raw);
    }

    var html = "";
    groups.forEach(function (group) {
      var groupKey = String(group && group.key ? group.key : "");
      var label = esc(group && group.label ? group.label : "");
      var items = Array.isArray(group && group.items) ? group.items : [];
      if (!items.length) {
        return;
      }
      html +=
        '<section class="opentt-search-group" data-group-key="' +
        esc(groupKey) +
        '">';
      html += '<div class="opentt-search-group-head">';
      if (groupKey === "trending" && openttSearchAssets.trendingIcon) {
        html +=
          '<span class="opentt-search-group-icon opentt-search-group-icon--fire" style="--opentt-trending-icon:url(\'' +
          esc(String(openttSearchAssets.trendingIcon || "")) +
          "')\"></span>";
      }
      html += '<h4 class="opentt-search-group-title">' + label + "</h4>";
      if (groupKey === "history") {
        html +=
          '<button type="button" class="opentt-search-clear-history" data-opentt-clear-history>' +
          esc(group && group.clearLabel ? group.clearLabel : "Očisti istoriju pretrage") +
          "</button>";
      }
      html += "</div>";
      html += '<div class="opentt-search-group-items">';
      items.forEach(function (item, index) {
        var title = esc(item && item.title ? item.title : "");
        var url = esc(item && item.url ? item.url : "#");
        var meta = esc(item && item.meta ? item.meta : "");
        var thumb = esc(item && item.thumb ? item.thumb : "");
        var entityType = String(item && item.entityType ? item.entityType : "");
        var entityId = parseInt(item && item.entityId ? item.entityId : 0, 10);
        var queryItem = String(item && item.query ? item.query : "");
        var queryAttr = queryItem
          ? ' data-opentt-search-query="' + esc(queryItem) + '"'
          : "";
        var entityTypeAttr = entityType
          ? ' data-opentt-entity-type="' + esc(entityType) + '"'
          : "";
        var entityIdAttr =
          !isNaN(entityId) && entityId > 0
            ? ' data-opentt-entity-id="' + String(entityId) + '"'
            : "";
        var href = queryItem ? "#" : url;
        var isMatchRow = !!(item && item.matchRow);
        html +=
          '<a class="opentt-search-item" href="' +
          href +
          '"' +
          queryAttr +
          entityTypeAttr +
          entityIdAttr +
          (isMatchRow ? ' data-opentt-item-type="match"' : "") +
          ">";
        html += '<span class="opentt-search-item-main">';
        if (groupKey === "trending") {
          html += trendingRankHtml(index + 1);
        }
        if (isMatchRow) {
          var homeNameRaw = String(item && item.homeName ? item.homeName : "");
          var awayNameRaw = String(item && item.awayName ? item.awayName : "");
          var homeThumb = esc(item && item.homeThumb ? item.homeThumb : "");
          var awayThumb = esc(item && item.awayThumb ? item.awayThumb : "");
          var scoreLabel = esc(item && item.scoreLabel ? item.scoreLabel : "");
          var leagueLabel = esc(item && item.leagueLabel ? item.leagueLabel : "");
          var dateLabel = esc(item && item.dateLabel ? item.dateLabel : "");
          var homeName = highlightText(homeNameRaw, highlightQuery);
          var awayName = highlightText(awayNameRaw, highlightQuery);
          html += '<span class="opentt-search-match-row">';
          html += '<span class="opentt-search-match-main">';
          html += '<span class="opentt-search-match-team is-home">';
          if (homeThumb) {
            html += '<span class="opentt-search-item-thumb is-mini"><img src="' + homeThumb + '" alt="" loading="lazy" decoding="async"></span>';
          }
          html += '<span class="opentt-search-match-team-name">' + homeName + "</span>";
          html += "</span>";
          html += '<span class="opentt-search-match-score">' + scoreLabel + "</span>";
          html += '<span class="opentt-search-match-team is-away">';
          html += '<span class="opentt-search-match-team-name">' + awayName + "</span>";
          if (awayThumb) {
            html += '<span class="opentt-search-item-thumb is-mini"><img src="' + awayThumb + '" alt="" loading="lazy" decoding="async"></span>';
          }
          html += "</span>";
          html += "</span>";
          html += '<span class="opentt-search-match-meta">' + leagueLabel;
          if (dateLabel) {
            html += " • " + dateLabel;
          }
          html += "</span>";
          html += "</span>";
        } else {
          if (thumb) {
            html +=
              '<span class="opentt-search-item-thumb"><img src="' +
              thumb +
              '" alt="" loading="lazy" decoding="async"></span>';
          }
          html += '<span class="opentt-search-item-text">';
          html += '<span class="opentt-search-item-title">' + title + "</span>";
          if (meta) {
            html += '<span class="opentt-search-item-meta">' + meta + "</span>";
          }
          html += "</span>";
        }
        html += "</span>";
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
    var backdrop = root.querySelector(".opentt-search-backdrop");
    var panel = root.querySelector(".opentt-search-panel");
    var closeBtn = root.querySelector(".opentt-search-close");
    var input = root.querySelector(".opentt-search-input");
    var suggestionBox = root.querySelector("[data-opentt-search-suggestion]");
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
    openttSearchAssets = data.assets && typeof data.assets === "object" ? data.assets : {};
    var promptText =
      i18n.prompt || ("Unesi najmanje " + String(minChars) + " karakter(a).");
    var loadingText = i18n.loading || "Pretraga...";
    var emptyText = i18n.empty || "Nema rezultata.";
    var historyLabel = i18n.historyLabel || "Istorija pretrage";
    var clearHistoryText = i18n.clearHistory || "Očisti istoriju pretrage";
    var didYouMeanText = i18n.didYouMean || "Da li ste mislili";
    var currentController = null;
    var discoveryCache = null;
    var historyCookieName = "opentt_search_history";
    var clientCookieName = "opentt_search_client";

    function readCookie(name) {
      var target = String(name || "") + "=";
      var parts = String(document.cookie || "").split(";");
      for (var i = 0; i < parts.length; i++) {
        var part = String(parts[i] || "").trim();
        if (part.indexOf(target) === 0) {
          return decodeURIComponent(part.substring(target.length));
        }
      }
      return "";
    }

    function writeCookie(name, value, days) {
      var expires = "";
      var ttl = parseInt(days || 30, 10);
      if (!isNaN(ttl) && ttl > 0) {
        var date = new Date();
        date.setTime(date.getTime() + ttl * 24 * 60 * 60 * 1000);
        expires = "; expires=" + date.toUTCString();
      }
      document.cookie =
        String(name || "") +
        "=" +
        encodeURIComponent(String(value || "")) +
        expires +
        "; path=/; SameSite=Lax";
    }

    function ensureClientToken() {
      var existing = String(readCookie(clientCookieName) || "").trim();
      if (existing) {
        return existing;
      }
      var token =
        "c" +
        Math.random().toString(36).slice(2, 10) +
        String(Date.now()).slice(-6);
      writeCookie(clientCookieName, token, 365);
      return token;
    }

    function getHistoryTerms() {
      var raw = readCookie(historyCookieName);
      if (!raw) {
        return [];
      }
      try {
        var parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
          return [];
        }
        return parsed
          .map(function (entry) {
            return String(entry || "").trim();
          })
          .filter(Boolean)
          .slice(0, 8);
      } catch (e) {
        return [];
      }
    }

    function pushHistoryTerm(term) {
      var value = String(term || "").trim();
      if (!value) {
        return;
      }
      var normalized = value.toLowerCase();
      var next = getHistoryTerms().filter(function (entry) {
        return String(entry || "").trim().toLowerCase() !== normalized;
      });
      next.unshift(value);
      next = next.slice(0, 8);
      writeCookie(historyCookieName, JSON.stringify(next), 60);
    }

    function buildHistoryGroup() {
      var terms = getHistoryTerms();
      if (!terms.length) {
        return null;
      }
      return {
        key: "history",
        label: historyLabel,
        clearLabel: clearHistoryText,
        items: terms.map(function (term) {
          return {
            title: term,
            meta: "Ponovo pretraži",
            query: term,
            url: "#",
          };
        }),
      };
    }

    function mergeDiscoveryGroups(serverGroups, includeHistory) {
      var groups = [];
      if (includeHistory) {
        var historyGroup = buildHistoryGroup();
        if (historyGroup) {
          groups.push(historyGroup);
        }
      }
      if (Array.isArray(serverGroups) && serverGroups.length) {
        groups = groups.concat(serverGroups);
      }
      return groups;
    }

    function renderSuggestion(queryValue, suggestionValue) {
      if (!suggestionBox) {
        return;
      }
      var query = String(queryValue || "").trim();
      var suggestion = String(suggestionValue || "").trim();
      if (
        !query ||
        !suggestion ||
        query.toLowerCase() === suggestion.toLowerCase()
      ) {
        suggestionBox.hidden = true;
        suggestionBox.innerHTML = "";
        return;
      }

      suggestionBox.hidden = false;
      suggestionBox.innerHTML =
        '<span class="opentt-search-suggestion-label">' +
        esc(didYouMeanText) +
        '</span> <button type="button" class="opentt-search-suggestion-btn" data-opentt-suggest-query="' +
        esc(suggestion) +
        '">"' +
        esc(suggestion) +
        '"</button>?';
    }

    function renderDiscovery(includeHistory) {
      if (discoveryCache) {
        renderSuggestion("", "");
        renderSearchGroups(results, mergeDiscoveryGroups(discoveryCache, !!includeHistory), "");
        return;
      }

      var ajaxUrl =
        window.openttFrontend && window.openttFrontend.ajaxUrl
          ? String(window.openttFrontend.ajaxUrl)
          : "";
      if (!ajaxUrl) {
        renderSuggestion("", "");
        renderSearchGroups(results, mergeDiscoveryGroups([], !!includeHistory), "");
        return;
      }

      results.innerHTML = '<p class="opentt-search-loading">' + esc(loadingText) + "</p>";
      var body = new URLSearchParams();
      body.set("action", "opentt_frontend_search");
      body.set("nonce", String(window.openttFrontend.searchNonce || ""));
      body.set("q", "");
      body.set("limit", String(limit));
      body.set("context", JSON.stringify(context));

      fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: body.toString(),
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (payload) {
          var serverGroups = [];
          if (payload && payload.success === true && payload.data) {
            serverGroups = Array.isArray(payload.data.groups) ? payload.data.groups : [];
          }
          discoveryCache = serverGroups;
          renderSuggestion("", "");
          renderSearchGroups(results, mergeDiscoveryGroups(serverGroups, !!includeHistory), "");
        })
        .catch(function () {
          renderSuggestion("", "");
          renderSearchGroups(results, mergeDiscoveryGroups([], !!includeHistory), "");
        });
    }

    function trackSearchClick(entityType, entityId) {
      var type = String(entityType || "").trim().toLowerCase();
      var id = parseInt(entityId || 0, 10);
      if (!type || isNaN(id) || id <= 0) {
        return;
      }
      var ajaxUrl =
        window.openttFrontend && window.openttFrontend.ajaxUrl
          ? String(window.openttFrontend.ajaxUrl)
          : "";
      if (!ajaxUrl) {
        return;
      }
      var body = new URLSearchParams();
      body.set("action", "opentt_frontend_search");
      body.set("nonce", String(window.openttFrontend.searchNonce || ""));
      body.set("track_click_type", type);
      body.set("track_click_id", String(id));
      body.set("track_click_client", ensureClientToken());

      var payload = body.toString();
      if (navigator && typeof navigator.sendBeacon === "function") {
        try {
          var blob = new Blob([payload], {
            type: "application/x-www-form-urlencoded; charset=UTF-8",
          });
          navigator.sendBeacon(ajaxUrl, blob);
          return;
        } catch (e) {}
      }

      fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: payload,
        keepalive: true,
      }).catch(function () {});
    }

    function closePanel() {
      if (panel.hidden) {
        return;
      }
      panel.hidden = true;
      if (backdrop) {
        backdrop.hidden = true;
      }
      toggle.setAttribute("aria-expanded", "false");
      unlockPageScroll();
    }

    function openPanel() {
      panel.hidden = false;
      if (backdrop) {
        backdrop.hidden = false;
      }
      toggle.setAttribute("aria-expanded", "true");
      lockPageScroll(panel);
      setTimeout(function () {
        input.focus();
      }, 0);
      if (!String(input.value || "").trim()) {
        renderDiscovery(true);
      }
    }

    toggle.addEventListener("click", function () {
      if (panel.hidden) {
        openPanel();
      } else {
        closePanel();
      }
    });
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        closePanel();
      });
    }
    if (backdrop) {
      backdrop.addEventListener("click", function () {
        closePanel();
      });
    }

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

    results.addEventListener("click", function (e) {
      var clearBtn =
        e.target && e.target.closest ? e.target.closest("[data-opentt-clear-history]") : null;
      if (clearBtn) {
        e.preventDefault();
        writeCookie(historyCookieName, "[]", 60);
        renderDiscovery(true);
        return;
      }

      var suggestBtn =
        e.target && e.target.closest
          ? e.target.closest("[data-opentt-suggest-query]")
          : null;
      if (suggestBtn) {
        e.preventDefault();
        var suggested = String(suggestBtn.getAttribute("data-opentt-suggest-query") || "").trim();
        if (!suggested) {
          return;
        }
        input.value = suggested;
        runSearch(suggested);
        return;
      }

      var qItem =
        e.target && e.target.closest
          ? e.target.closest("[data-opentt-search-query]")
          : null;
      if (qItem) {
        e.preventDefault();
        var q = String(qItem.getAttribute("data-opentt-search-query") || "").trim();
        if (!q) {
          return;
        }
        input.value = q;
        pushHistoryTerm(q);
        runSearch(q);
        return;
      }

      var link =
        e.target && e.target.closest ? e.target.closest(".opentt-search-item") : null;
      if (link) {
        var entityType = String(link.getAttribute("data-opentt-entity-type") || "").trim();
        var entityId = parseInt(link.getAttribute("data-opentt-entity-id") || "0", 10);
        if (entityType && !isNaN(entityId) && entityId > 0) {
          trackSearchClick(entityType, entityId);
        }
        var term = String(input.value || "").trim();
        if (term.length >= minChars) {
          pushHistoryTerm(term);
        }
      }
    });

    input.addEventListener("focus", function () {
      if (!String(input.value || "").trim()) {
        renderDiscovery(true);
      }
    });

    input.addEventListener("blur", function () {
      if (!String(input.value || "").trim()) {
        setTimeout(function () {
          renderDiscovery(false);
        }, 100);
      }
    });

    input.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") {
        return;
      }
      var term = String(input.value || "").trim();
      if (term.length < minChars) {
        return;
      }
      pushHistoryTerm(term);
    });

    var runSearch = debounce(function (value) {
      var query = String(value || "").trim();
      if (query.length === 0) {
        renderSuggestion("", "");
        renderDiscovery(document.activeElement === input);
        return;
      }
      if (query.length < minChars) {
        renderSuggestion("", "");
        results.innerHTML = '<p class="opentt-search-empty">' + esc(promptText) + "</p>";
        return;
      }

      var ajaxUrl =
        window.openttFrontend && window.openttFrontend.ajaxUrl
          ? String(window.openttFrontend.ajaxUrl)
          : "";
      if (!ajaxUrl) {
        renderSuggestion("", "");
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
            renderSuggestion("", "");
            results.innerHTML = '<p class="opentt-search-empty">' + esc(emptyText) + "</p>";
            return;
          }
          var data = payload.data && typeof payload.data === "object" ? payload.data : {};
          renderSuggestion(query, data.suggestion || "");
          renderSearchGroups(results, data.groups || [], query);
        })
        .catch(function () {
          renderSuggestion("", "");
          results.innerHTML = '<p class="opentt-search-empty">' + esc(emptyText) + "</p>";
        });
    }, 170);

    input.addEventListener("input", function () {
      runSearch(input.value || "");
    });
  }

  function parseStandingsShareData(root) {
    if (!root) {
      return null;
    }
    var node = root.querySelector(".opentt-standings-share-data");
    if (!node) {
      return null;
    }
    try {
      return JSON.parse(String(node.textContent || "").trim());
    } catch (err) {
      return null;
    }
  }

  function decodeHtmlEntities(value) {
    var raw = String(value || "");
    if (!raw) {
      return raw;
    }
    var txt = document.createElement("textarea");
    txt.innerHTML = raw;
    var decoded = txt.value;
    txt.innerHTML = decoded;
    return txt.value || decoded;
  }

  function createStandingsExportNode(payload) {
    var rows = Array.isArray(payload && payload.rows) ? payload.rows : [];
    var round = parseInt(payload && payload.round ? payload.round : 0, 10);
    var promotionCut = parseInt(payload && payload.promotionCut ? payload.promotionCut : 0, 10);
    var subtitle = round > 0 ? "TABELA " + String(round) + ". KOLA" : "TABELA";
    var leagueTitle = String((payload && payload.league) || "Liga");
    var seasonLabel = String((payload && payload.season) || "Sezona");
    var leagueLine = (leagueTitle + " " + seasonLabel).trim();
    var layoutTop = 188;
    var layoutBottom = 940;
    var maxTableHeight = layoutBottom - layoutTop;
    var rowH = Math.max(34, Math.min(54, Math.floor((maxTableHeight - 76) / Math.max(1, rows.length))));
    var tableHeight = 62 + rows.length * rowH + 28;
    if (tableHeight > maxTableHeight) {
      tableHeight = maxTableHeight;
    }
    var tableTop = Math.round(layoutTop + (maxTableHeight - tableHeight) / 2);

    var rowsHtml = "";
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i] || {};
      var clubName = decodeHtmlEntities(String(row.club || ""));
      var rowClass = "opentt-export-row";
      if (promotionCut > 0 && i === promotionCut - 1) {
        rowClass += " is-cut";
      } else if (i === 0) {
        rowClass += " is-first";
      }
      if (row.highlight) {
        rowClass += " is-highlight";
      }
      rowsHtml +=
        '<div class="' +
        rowClass +
        '" style="height:' +
        String(rowH) +
        'px;">' +
        '<div class="col col-rank col-num">' +
        esc(String(row.rank || "")) +
        '</div>' +
        '<div class="col col-club"><span class="club-logo-wrap"><img src="' +
        esc(String(row.logoUrl || "")) +
        '" alt="" loading="eager" decoding="sync"></span><span class="club-name">' +
        esc(clubName) +
        "</span></div>" +
        '<div class="col col-num">' +
        esc(String(row.played || "")) +
        "</div>" +
        '<div class="col col-num">' +
        esc(String(row.wins || "")) +
        "</div>" +
        '<div class="col col-num">' +
        esc(String(row.losses || "")) +
        "</div>" +
        '<div class="col col-num">' +
        esc(String(row.diff || "")) +
        "</div>" +
        '<div class="col col-num">' +
        esc(String(row.points || "")) +
        "</div>" +
        "</div>";
    }

    var host = document.createElement("div");
    host.style.cssText =
      "position:fixed;left:-20000px;top:0;width:1080px;height:1080px;z-index:-1;pointer-events:none;";
    host.innerHTML =
      '<style>' +
      ".opentt-export-card{position:relative;width:1080px;height:1080px;overflow:hidden;background:linear-gradient(165deg,#03153e 0%,#07296f 58%,#0a3e91 100%);font-family:inherit;color:#fff;}" +
      ".opentt-export-header{position:absolute;left:56px;right:56px;top:44px;display:flex;align-items:center;gap:16px;}" +
      ".opentt-export-corner-logo{width:120px;height:88px;display:flex;align-items:center;justify-content:center;}" +
      ".opentt-export-corner-logo img{width:auto;height:auto;max-width:112px;max-height:78px;object-fit:contain;}" +
      ".opentt-export-head-main{text-align:left;display:flex;flex-direction:column;gap:2px;}" +
      ".opentt-export-head-main .league{font-size:50px;font-weight:700;line-height:1.02;color:#ffffff;}" +
      ".opentt-export-head-main .round{font-size:40px;font-weight:800;line-height:1.02;color:#ffdf44;letter-spacing:.01em;}" +
      ".opentt-export-table{position:absolute;left:56px;right:56px;border-radius:22px;background:rgba(3,14,38,.62);overflow:hidden;}" +
      ".opentt-export-table .table-watermark{position:absolute;left:50%;top:54%;width:116%;height:116%;transform:translate(-50%,-50%) rotate(-12deg);object-fit:contain;opacity:.14;filter:blur(1.4px);pointer-events:none;z-index:0;}" +
      ".opentt-export-head-row{height:62px;display:grid;grid-template-columns:7% 47% 9% 9% 9% 9% 10%;align-items:center;padding:0 12px;background:rgba(8,30,82,.56);font-size:24px;font-weight:600;color:rgba(236,245,255,.95);}" +
      ".opentt-export-head-row > div{display:flex;align-items:center;justify-content:center;}" +
      ".opentt-export-head-row .club-head{justify-content:flex-start;padding-left:8px;}" +
      ".opentt-export-body{position:absolute;left:0;right:0;top:62px;bottom:0;padding:8px 12px 20px;}" +
      ".opentt-export-row{display:grid;grid-template-columns:7% 47% 9% 9% 9% 9% 10%;align-items:center;padding:0 12px;font-size:28px;color:rgba(236,245,255,.95);border-bottom:1px solid rgba(255,255,255,.15);}" +
      ".opentt-export-row.is-highlight{background:rgba(0,132,255,.12);}" +
      ".opentt-export-row.is-cut{border-bottom:2px solid #ffdf44;}" +
      ".opentt-export-row .col{display:flex;align-items:center;justify-content:center;}" +
      ".opentt-export-row .col-club{justify-content:flex-start;gap:12px;padding-right:8px;}" +
      ".opentt-export-row .club-logo-wrap{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex:0 0 38px;}" +
      ".opentt-export-row .club-logo-wrap img{width:30px;height:30px;object-fit:contain;}" +
      ".opentt-export-row .club-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}" +
      ".opentt-export-row .col-num{font-size:24px;font-weight:600;}" +
      ".opentt-export-head-row,.opentt-export-body{position:relative;z-index:1;}" +
      ".opentt-export-foot{position:absolute;left:56px;right:56px;bottom:40px;height:58px;display:flex;align-items:center;justify-content:space-between;color:rgba(205,223,252,.96);font-size:26px;font-weight:500;}" +
      ".opentt-export-foot .foot-brand{width:150px;height:58px;display:flex;align-items:center;justify-content:flex-start;}" +
      ".opentt-export-foot .foot-brand img{width:auto;height:auto;max-width:142px;max-height:54px;object-fit:contain;}" +
      ".opentt-export-foot .foot-text{text-align:right;flex:1;}" +
      "</style>" +
      '<div class="opentt-export-card">' +
      '<header class="opentt-export-header">' +
      '<div class="opentt-export-corner-logo">' +
      (payload && payload.competitionLogoUrl
        ? '<img src="' +
          esc(String(payload.competitionLogoUrl || "")) +
          '" alt="" loading="eager" decoding="sync">'
        : "") +
      "</div>" +
      '<div class="opentt-export-head-main"><div class="league">' +
      esc(leagueLine) +
      '</div><div class="round">' +
      esc(subtitle) +
      "</div></div>" +
      "</header>" +
      '<section class="opentt-export-table" style="top:' +
      String(tableTop) +
      "px;height:" +
      String(tableHeight) +
      'px;">' +
      (payload && payload.bgAltUrl
        ? '<img class="table-watermark" src="' +
          esc(String(payload.bgAltUrl || "")) +
          '" alt="" loading="eager" decoding="sync">'
        : "") +
      '<div class="opentt-export-head-row"><div>#</div><div class="club-head">Klub</div><div>Utak</div><div>Pob</div><div>Por</div><div>+/-</div><div>Bod</div></div>' +
      '<div class="opentt-export-body">' +
      rowsHtml +
      "</div>" +
      "</section>" +
      '<footer class="opentt-export-foot">' +
      '<span class="foot-brand">' +
      (payload && payload.brandLogoUrl
        ? '<img src="' +
          esc(String(payload.brandLogoUrl || "")) +
          '" alt="" loading="eager" decoding="sync">'
        : "") +
      '</span><span class="foot-text">' +
      esc(String((payload && payload.footer) || "Tabela preuzeta sa sajta stkb.rs")) +
      "</span>" +
      "</footer>" +
      "</div>";
    document.body.appendChild(host);
    return host;
  }

  function generateStandingsImage(payload) {
    return new Promise(function (resolve, reject) {
      var rows = Array.isArray(payload && payload.rows) ? payload.rows : [];
      if (!rows.length) {
        reject(new Error("Nema podataka za tabelu."));
        return;
      }
      if (typeof window.html2canvas !== "function") {
        reject(new Error("html2canvas nije dostupan."));
        return;
      }
      var host = createStandingsExportNode(payload);
      var card = host ? host.querySelector(".opentt-export-card") : null;
      if (!card) {
        if (host && host.parentNode) {
          host.parentNode.removeChild(host);
        }
        reject(new Error("Neuspešno kreiranje export šablona."));
        return;
      }
      window
        .html2canvas(card, {
          backgroundColor: null,
          scale: 1,
          width: 1080,
          height: 1080,
          useCORS: true,
          allowTaint: false,
          logging: false,
        })
        .then(function (canvas) {
          canvas.toBlob(
            function (blob) {
              if (host && host.parentNode) {
                host.parentNode.removeChild(host);
              }
              if (!blob) {
                reject(new Error("Neuspešno generisanje slike."));
                return;
              }
              resolve(blob);
            },
            "image/png",
            1
          );
        })
        .catch(function () {
          if (host && host.parentNode) {
            host.parentNode.removeChild(host);
          }
          reject(new Error("Neuspešno generisanje slike."));
        });
    });
  }

  function downloadStandingsBlob(blob, payload) {
    var fileName =
      "stkb-tabela-" +
      String((payload && payload.league) || "liga")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "") +
      "-" +
      String((payload && payload.season) || "sezona")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "") +
      ".png";
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () {
      URL.revokeObjectURL(url);
    }, 1000);
  }

  function shareStandingsBlob(blob, payload) {
    var fileName = "stkb-tabela.png";
    var file = new File([blob], fileName, { type: "image/png" });
    if (
      navigator &&
      typeof navigator.share === "function" &&
      typeof navigator.canShare === "function" &&
      navigator.canShare({ files: [file] })
    ) {
      return navigator.share({
        title: String((payload && payload.league) || "Tabela"),
        text: "Tabela preuzeta sa stkb.rs",
        files: [file],
      });
    }
    return Promise.reject(new Error("share_not_supported"));
  }

  function initStandingsShare(root) {
    if (!root || root.dataset.openttStandingsShareReady === "1") {
      return;
    }
    var payload = parseStandingsShareData(root);
    if (!payload || !Array.isArray(payload.rows) || !payload.rows.length) {
      return;
    }

    var shareBtn = root.querySelector('[data-opentt-standings-share-btn="share"]');
    var downloadBtn = root.querySelector('[data-opentt-standings-share-btn="download"]');
    if (!shareBtn || !downloadBtn) {
      return;
    }

    root.dataset.openttStandingsShareReady = "1";
    var busy = false;

    function setBusy(state) {
      busy = !!state;
      shareBtn.disabled = busy;
      downloadBtn.disabled = busy;
      shareBtn.classList.toggle("is-busy", busy);
      downloadBtn.classList.toggle("is-busy", busy);
    }

    function runAction(type) {
      if (busy) {
        return;
      }
      setBusy(true);
      generateStandingsImage(payload)
        .then(function (blob) {
          if (type === "download") {
            downloadStandingsBlob(blob, payload);
            return null;
          }
          return shareStandingsBlob(blob, payload).catch(function (err) {
            if (err && err.message === "share_not_supported") {
              downloadStandingsBlob(blob, payload);
              return null;
            }
            throw err;
          });
        })
        .catch(function () {
          window.alert("Neuspešno generisanje slike tabele. Pokušaj ponovo.");
        })
        .finally(function () {
          setBusy(false);
        });
    }

    shareBtn.addEventListener("click", function () {
      runAction("share");
    });
    downloadBtn.addEventListener("click", function () {
      runAction("download");
    });
  }

  function initAllStandingsShare() {
    var roots = document.querySelectorAll('[data-opentt-standings-share="1"]');
    for (var i = 0; i < roots.length; i++) {
      initStandingsShare(roots[i]);
    }
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
      initAllStandingsShare();
    });
  } else {
    initAllMatchesLists();
    initAllSearches();
    initAllStandingsShare();
  }
})();
    function escapeRegex(value) {
      return String(value || "").replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    }

    function highlightText(value, query) {
      var raw = String(value || "");
      var q = String(query || "").trim();
      if (!q) {
        return esc(raw);
      }
      var re = null;
      try {
        re = new RegExp("(" + escapeRegex(q) + ")", "gi");
      } catch (err) {
        return esc(raw);
      }
      var parts = raw.split(re);
      if (!parts || parts.length <= 1) {
        return esc(raw);
      }
      var html = "";
      for (var i = 0; i < parts.length; i++) {
        var part = String(parts[i] || "");
        if (!part) {
          continue;
        }
        if (part.toLowerCase() === q.toLowerCase()) {
          html += '<strong class="opentt-search-hit">' + esc(part) + "</strong>";
        } else {
          html += esc(part);
        }
      }
      return html || esc(raw);
    }
