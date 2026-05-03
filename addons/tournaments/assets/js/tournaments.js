(function () {
  function initTournament(root) {
    if (!root || root.dataset.openttTournamentBound === '1') {
      return;
    }
    root.dataset.openttTournamentBound = '1';

    var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-opentt-tournament-tab]'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-opentt-tournament-category]'));
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var id = tab.getAttribute('data-opentt-tournament-tab');
        tabs.forEach(function (item) {
          item.classList.toggle('is-active', item === tab);
        });
        panels.forEach(function (panel) {
          var active = panel.getAttribute('data-opentt-tournament-category') === id;
          panel.classList.toggle('is-active', active);
          if (active) {
            initRoundNav(panel);
          }
        });
      });
    });
    panels.forEach(initRoundNav);
  }

  function initRoundNav(scope) {
    var bracket = scope.querySelector('[data-opentt-bracket]');
    var nav = scope.querySelector('[data-opentt-round-nav]');
    if (!bracket || !nav || nav.dataset.openttRoundBound === '1') {
      return;
    }
    nav.dataset.openttRoundBound = '1';
    var rounds = Array.prototype.slice.call(bracket.querySelectorAll('[data-opentt-round]'));
    var prev = nav.querySelector('[data-opentt-round-prev]');
    var next = nav.querySelector('[data-opentt-round-next]');
    var label = nav.querySelector('[data-opentt-round-current]');
    var index = 0;

    function render() {
      rounds.forEach(function (round, i) {
        round.classList.toggle('is-active', i === index);
      });
      if (label && rounds[index]) {
        label.textContent = rounds[index].getAttribute('data-opentt-round-label') || '';
      }
      if (prev) {
        prev.disabled = index <= 0;
      }
      if (next) {
        next.disabled = index >= rounds.length - 1;
      }
    }

    if (prev) {
      prev.addEventListener('click', function () {
        index = Math.max(0, index - 1);
        render();
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        index = Math.min(rounds.length - 1, index + 1);
        render();
      });
    }
    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.slice.call(document.querySelectorAll('[data-opentt-tournament]')).forEach(initTournament);
  });
})();

