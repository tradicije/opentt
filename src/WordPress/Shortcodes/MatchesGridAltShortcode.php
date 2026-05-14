<?php
/**
 * OpenTT - Table Tennis Management Plugin
 * Copyright (C) 2026 Aleksa Dimitrijević
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 */

namespace OpenTT\Unified\WordPress\Shortcodes;

final class MatchesGridAltShortcode
{
    public static function render($atts, array $deps)
    {
        $renderDefault = isset($deps['render_default']) && is_callable($deps['render_default'])
            ? $deps['render_default']
            : null;

        if ($renderDefault === null) {
            return '';
        }

        $baseHtml = (string) $renderDefault($atts);
        if ($baseHtml === '') {
            return '';
        }

        return self::assetsOnce() . '<div class="opentt-matches-grid-alt">' . $baseHtml . '</div>';
    }

    private static function assetsOnce()
    {
        static $printed = false;
        if ($printed) {
            return '';
        }
        $printed = true;

        ob_start();
        ?>
        <style id="opentt-matches-grid-alt-style">
        .opentt-matches-grid-alt .opentt-grid,
        .opentt-matches-grid-alt .opentt-grid.cols-2,
        .opentt-matches-grid-alt .opentt-grid.cols-3,
        .opentt-matches-grid-alt .opentt-grid.cols-4,
        .opentt-matches-grid-alt .opentt-grid.cols-5,
        .opentt-matches-grid-alt .opentt-grid.cols-6 {
          display: grid !important;
          grid-template-columns: 1fr !important;
          gap: 14px !important;
        }

        .opentt-matches-grid-alt .opentt-alt-card {
          position: relative;
          border: 1px solid #FED7AA26;
          border-radius: 0;
          background: transparent;
          padding: 14px 14px 12px;
        }

        .opentt-matches-grid-alt .opentt-alt-card-top {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 8px;
          font-family: "DM Sans", sans-serif;
          font-size: 12px;
          color: #FED7AA;
        }

        .opentt-matches-grid-alt .opentt-alt-match-no { font-weight: 600; }
        .opentt-matches-grid-alt .opentt-alt-date { opacity: .92; }

        .opentt-matches-grid-alt .opentt-alt-score {
          font-family: "Lora", serif;
          font-size: 34px;
          line-height: 1;
          color: #FBBF24;
          font-weight: 700;
          text-align: center;
          margin: 6px 0 8px;
        }

        .opentt-matches-grid-alt .opentt-alt-teams {
          font-family: "Lora", serif;
          font-size: 22px;
          line-height: 1.25;
          text-align: center;
          color: #FED7AA;
        }

        .opentt-matches-grid-alt .opentt-alt-sep {
          height: 1px;
          background: #FED7AA26;
          margin: 10px 0 8px;
        }

        .opentt-matches-grid-alt .opentt-alt-sets {
          font-family: "DM Sans", sans-serif;
          font-size: 13px;
          line-height: 1.35;
          color: #FED7AA;
          text-align: center;
        }

        .opentt-matches-grid-alt .opentt-alt-win {
          font-family: "Lora", serif;
          font-weight: 700;
          color: #FBBF24;
        }

        .opentt-matches-grid-alt .opentt-alt-lose {
          font-family: "Lora", serif;
          font-style: italic;
          font-weight: 400;
          color: rgba(254, 215, 170, .72);
        }

        .opentt-matches-grid-alt .opentt-alt-link {
          text-decoration: none;
          display: block;
        }

        .opentt-matches-grid-alt .opentt-alt-rendered .opentt-grid-wrapper,
        .opentt-matches-grid-alt .opentt-alt-rendered .opentt-grid-round-heading {
          display: none !important;
        }

        .opentt-matches-grid-alt .opentt-item img,
        .opentt-matches-grid-alt .team img,
        .opentt-matches-grid-alt .opentt-club-fallback-image {
          display: none !important;
        }
        </style>
        <script>
        (function(){
          function roman(n){
            var r=["I","II","III","IV","V","VI","VII","VIII","IX","X","XI","XII","XIII","XIV","XV","XVI","XVII","XVIII","XIX","XX","XXI","XXII","XXIII","XXIV","XXV","XXVI","XXVII","XXVIII","XXIX","XXX"];
            return r[n-1] || String(n);
          }
          function shortDate(s){
            if(!s){return "";}
            var m=s.match(/^(\d{1,2})\.(\d{1,2})\./);
            if(!m){return s;}
            var d=parseInt(m[1],10), mo=parseInt(m[2],10);
            var months=["jan","feb","mar","apr","maj","jun","jul","avg","sep","okt","nov","dec"];
            return d + ". " + (months[mo-1] || "");
          }
          function buildCard(item, idx){
            var teams=item.querySelectorAll('.team');
            if(!teams || teams.length<2){return null;}
            var t1=(teams[0].querySelector('span')||{}).textContent||'Domaćin';
            var t2=(teams[1].querySelector('span')||{}).textContent||'Gost';
            t1=t1.trim(); t2=t2.trim();
            var s1=parseInt(((teams[0].querySelector('strong')||{}).textContent||'').trim(),10);
            var s2=parseInt(((teams[1].querySelector('strong')||{}).textContent||'').trim(),10);
            var score=(isFinite(s1)&&isFinite(s2)) ? (s1+' : '+s2) : '- : -';
            var c1='', c2='';
            if(isFinite(s1)&&isFinite(s2)){
              if(s1>s2){c1='opentt-alt-win'; c2='opentt-alt-lose';}
              else if(s2>s1){c1='opentt-alt-lose'; c2='opentt-alt-win';}
            }
            var href='#';
            var a=item.querySelector('a[href]');
            if(a){href=a.getAttribute('href')||'#';}
            var date=shortDate(item.getAttribute('data-match-date-display')||'');

            var link=document.createElement('a');
            link.className='opentt-alt-link';
            link.href=href;
            link.innerHTML=''
              +'<article class="opentt-alt-card">'
              +'<div class="opentt-alt-card-top"><span class="opentt-alt-match-no">Utakmica '+roman(idx)+'</span><span class="opentt-alt-date">'+date+'</span></div>'
              +'<div class="opentt-alt-score">'+score+'</div>'
              +'<div class="opentt-alt-teams"><span class="'+c1+'">'+t1+'</span> ~ <span class="'+c2+'">'+t2+'</span></div>'
              +'<div class="opentt-alt-sep"></div>'
              +'<div class="opentt-alt-sets">Setovi: —</div>'
              +'</article>';
            return link;
          }

          function renderScope(scope){
            if(scope.classList.contains('opentt-alt-rendered')){return true;}
            var grids=scope.querySelectorAll('.opentt-grid-wrapper .opentt-grid');
            if(!grids.length){return false;}

            for(var g=0; g<grids.length; g++){
              var grid=grids[g];
              var items=grid.querySelectorAll('.opentt-item');
              if(!items.length){continue;}

              var list=document.createElement('div');
              list.className='opentt-grid opentt-alt-grid cols-1';

              var lastRound='';
              var roundIndex=0;
              for(var i=0;i<items.length;i++){
                var it=items[i];
                var round=it.getAttribute('data-kolo-slug')||'';
                if(round!==lastRound){ lastRound=round; roundIndex=0; }
                roundIndex++;
                var card=buildCard(it, roundIndex);
                if(card){ list.appendChild(card); }
              }

              if(list.children.length){
                grid.parentNode.insertBefore(list, grid);
              }
            }

            scope.classList.add('opentt-alt-rendered');
            return true;
          }

          function renderAll(){
            var scopes=document.querySelectorAll('.opentt-matches-grid-alt');
            var ok=false;
            for(var i=0;i<scopes.length;i++){
              if(renderScope(scopes[i])){ok=true;}
            }
            return ok;
          }

          function boot(){
            var tries=0,max=20;
            var t=setInterval(function(){
              tries++;
              var done=renderAll();
              if(done || tries>=max){clearInterval(t);} 
            },250);

            var mo=new MutationObserver(function(){ renderAll(); });
            mo.observe(document.body,{childList:true,subtree:true});
          }

          if(document.readyState==='loading'){
            document.addEventListener('DOMContentLoaded',boot);
          }else{boot();}
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }
}
