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

    var rawData = dataNode.value || dataNode.textContent || "";
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

    render();
  }

  function initAllMatchesLists() {
    var roots = document.querySelectorAll('[data-opentt-matches-list="1"]');
    for (var i = 0; i < roots.length; i++) {
      initMatchesList(roots[i]);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAllMatchesLists);
  } else {
    initAllMatchesLists();
  }
})();
