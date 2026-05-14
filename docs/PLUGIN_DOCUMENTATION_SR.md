# OpenTT Dokumentacija (Srpski)

Ovaj dokument je detaljno korisničko i operativno uputstvo za OpenTT WordPress plugin.
Namenjen je administratorima sajtova, urednicima sadržaja, operaterima liga, klubovima i tehničkim osobama koje održavaju sistem.

## 1. Šta je OpenTT

OpenTT je WordPress plugin za vođenje, prikaz i arhiviranje stonoteniskih takmičenja.
Plugin objedinjuje:
- administraciju utakmica i rezultata,
- evidenciju klubova i igrača,
- prikaz tabela, statistika i rang-lista,
- import/export podataka,
- migraciju sa starijih struktura.

Ključna arhitekturna odluka: meč podaci (utakmice, partije i setovi) čuvaju se u namenskim DB tabelama radi performansi i pouzdanosti.

## 2. Osnovne karakteristike

- Jedinstven admin panel za OpenTT.
- Podrška za klubove (`klub`) i igrače (`igrac`) kroz WordPress sadržajni model.
- DB model za masivne meč podatke.
- Sistem shortcode-ova za frontend prikaz.
- Onboarding tok za nove instalacije.
- Vizuelna podešavanja i CSS override mehanizam.
- Uvoz/izvoz podataka kroz JSON paket.
- Legacy kompatibilnost ruta i delova sadržaja.

## 3. Zahtevi

- WordPress okruženje sa pristupom bazi podataka.
- Dozvole za kreiranje/izmenu tabela i čuvanje opcija.
- Preporuka: redovan backup baze i `uploads` pre većih import/migration operacija.

## 4. Instalacija

1. Postaviti plugin fajlove u WordPress plugin direktorijum.
2. Aktivirati plugin kroz WP Admin > Plugins.
3. Pri aktivaciji plugin:
- registruje potrebne rute i pravila prepisivanja,
- proverava i po potrebi migrira šemu,
- priprema onboarding stanje,
- proverava i kreira podrazumevane stranice.

## 5. Prvo pokretanje (Onboarding)

Na svežoj instalaciji OpenTT vodi administratora kroz početno podešavanje.
Tipični koraci:
- osnovna konfiguracija,
- inicijalne stranice,
- provera dostupnosti podataka,
- usmeravanje na glavne operacije (takmičenja, klubovi, igrači, mečevi).

## 6. Arhitektura podataka

### 6.1 Entiteti

- Klubovi: WordPress post type `klub`.
- Igrači: WordPress post type `igrac`.
- Meč podaci: posebne DB tabele.

### 6.2 Tabele (prefiks zavisi od WP instalacije)

- `*_opentt_matches`
- `*_opentt_games`
- `*_opentt_sets`
- `*_opentt_games_pending_submissions` (pending unosi)

### 6.3 Zašto ovaj model

- Brže upite i obradu većeg broja sezona/mečeva.
- Stabilniji izračun statistika i tabela.
- Jednostavniji import/export i migracije istorijskih podataka.

## 7. Admin moduli i funkcionalnosti

OpenTT admin obično uključuje sledeće celine:
- Dashboard
- Utakmice
- Klubovi
- Igrači
- Takmičenja
- Import/Export
- Customize
- Settings

Dodatno:
- live pretraga i filteri,
- pomoćni admin tokovi (onboarding, migracije, validacije),
- dijagnostika i pomoćne akcije.

## 8. Takmičenja, liga i sezona

OpenTT koristi liga/sezona logiku kroz standardizovane slug vrednosti i pravila takmičenja.
Podržano je:
- mapiranje legacy formata,
- izbor bodovnog sistema,
- promocija/ispadanje pravila,
- izračun tabela po sezoni i takmičenju.

## 9. Upravljanje utakmicama

Mečevi se vode kroz DB model sa povezivanjem na klubove i igrače.
Uobičajeni elementi:
- datum i vreme,
- domaćin/gost,
- rezultat meča,
- partije i setovi,
- status odigranosti,
- kolo/runda,
- liga i sezona.

## 10. Klubovi i igrači

### Klubovi

Moguće je čuvati i prikazivati:
- naziv i logo,
- grad/opština,
- kontakt podatke,
- dres/loptice/sala informacije,
- veze ka vestima i povezanim prikazima.

### Igrači

Moguće je čuvati i prikazivati:
- profilne podatke,
- povezanost sa klubom,
- statistiku i učinak,
- istoriju transfera (zavisno od dostupnih podataka).

## 11. Shortcode sistem

OpenTT koristi `opentt_*` shortcode prefiks.

### 11.1 Lista podržanih shortcode-ova

- `[opentt_auth]`
- `[opentt_auth_menu]`
- `[opentt_profile]`
- `[opentt_search]`
- `[opentt_matches]`
- `[opentt_matches_grid]`
- `[opentt_matches_list]`
- `[opentt_match_id]`
- `[opentt_featured_match]`
- `[opentt_featured_player]`
- `[opentt_standings_short]`
- `[opentt_standings_table]`
- `[opentt_match_games]`
- `[opentt_h2h]`
- `[opentt_mvp]`
- `[opentt_match_report]`
- `[opentt_match_video]`
- `[opentt_match_teams_short]`
- `[opentt_home_club]`
- `[opentt_away_club]`
- `[opentt_club]`
- `[opentt_match_teams]`
- `[opentt_top_players]`
- `[opentt_players]`
- `[opentt_club_news]`
- `[opentt_club_featured]`
- `[opentt_player_news]`
- `[opentt_related_posts]`
- `[opentt_club_info]`
- `[opentt_club_card]`
- `[opentt_competition_info]`
- `[opentt_club_form]`
- `[opentt_player_stats]`
- `[opentt_team_stats]`
- `[opentt_player_transfers]`
- `[opentt_player_info]`
- `[opentt_competitions]`
- `[opentt_clubs]`

### 11.2 Najčešće korišćeni shortcode-ovi

- Mečevi/grid: `[opentt_matches_grid]`
- Tabela: `[opentt_standings_table]`
- Utakmica partije: `[opentt_match_games]`
- Klub info: `[opentt_club_info]`
- Team stats: `[opentt_team_stats]`
- Igrači kluba: `[opentt_players]`
- Top igrači: `[opentt_top_players]`

### 11.3 Primeri

```text
[opentt_matches_grid liga="prva-liga" sezona="2025-26" limit="12" filter="true"]
```

```text
[opentt_standings_table liga="prva-liga" sezona="2025-26" highlight="stk-bubusinac"]
```

```text
[opentt_club_info]
[opentt_team_stats filter="true"]
[opentt_players]
```

Napomena: tačan skup atributa može varirati po shortcode-u; preporuka je da se koristi OpenTT admin builder/insert alati gde su dostupni.

## 12. Single stranice i kontekst

OpenTT shortcode-ovi često rade kontekstualno:
- na `single-klub` stranici automatski koriste trenutni klub,
- na `single-igrac` stranici koriste trenutnog igrača,
- na takmičarskim stranicama koriste liga/sezona kontekst.

To smanjuje potrebu za ručnim prosleđivanjem atributa.

## 13. Frontend pretraga

Plugin podržava frontend pretragu (uključujući AJAX tokove).
Tipični elementi:
- unos upita,
- grupisani rezultati,
- kontekstualni predlozi,
- trend/preporuke.

## 14. Import/Export

### 14.1 Export

Moguće sekcije:
- takmičenja,
- klubovi,
- igrači,
- utakmice,
- partije,
- setovi.

### 14.2 Import

Preporučeni tok:
1. Učitavanje JSON paketa.
2. Validacija strukture i sadržaja.
3. Pregled upozorenja (duplikati, reference, nedostajući entiteti).
4. Potvrda i izvršavanje.

### 14.3 Dobre prakse

- Uvek napraviti backup pre importa.
- Uvoz raditi prvo na staging okruženju.
- Proveriti sample prikaz na frontendu nakon importa.

## 15. Migracije i legacy kompatibilnost

OpenTT uključuje migracione i kompatibilne mehanizme za prelazak sa starijih struktura.
Može uključivati:
- mapiranja liga/sezona,
- mapiranje internih ID/ključeva,
- SQL migracione skripte,
- fallback logiku za stare rute/sadržaj.

Preporuka: migracije sprovoditi planski, uz test i backup.

## 16. Teme, template-i i override

OpenTT podržava:
- plugin fallback template-e,
- prioritet theme override-a,
- block i klasične teme.

Ako tema nema specifične šablone, plugin koristi svoje fallback varijante.

## 17. Stilizacija i prilagođavanje

Dostupno:
- globalna vizuelna podešavanja,
- custom CSS globalno,
- custom CSS po shortcode-u,
- modularni CSS fajlovi po funkcionalnim celinama.

Preporuka:
- čuvati custom stilove organizovano po sekcijama,
- koristiti child temu za veće override izmene.

## 18. Lokalizacija

- Admin UI jezički fajlovi: `languages/admin-ui-<lang>.txt`.
- Format linije: `english_reference = prevod`.
- Novi jezik se automatski detektuje ako je fajl ispravno imenovan.

## 19. Uloge i dozvole

Plugin koristi WordPress capabilities model.
Za administrativne operacije potrebne su odgovarajuće dozvole (tipično uredničke/administratorske).

## 20. Performanse

Preporuke:
- držati bazu optimizovanom,
- redovno čistiti nepotrebne revizije i transient-e,
- koristiti cache sloj na produkciji,
- planirati import/migracije van vršnih termina.

Za velike istorije (više sezona):
- DB model mečeva je već dobra osnova,
- dodatno razmotriti sezonske agregate gde je potrebno.

## 21. Bezbednost

OpenTT koristi WordPress sigurnosne obrasce (nonce, sanitizacija ulaza, capability provere) u administrativnim i AJAX tokovima.
Dobre prakse:
- redovan update WP jezgra i plugin-a,
- ograničen admin pristup,
- SSL i backup politike.

## 22. Operativni tok za klub/savez (preporuka)

1. Podesiti takmičenje (liga, sezona, pravila).
2. Uneti/uvezti klubove i igrače.
3. Uneti raspored i rezultate.
4. Proveriti tabele/statistike.
5. Objaviti frontend stranice sa shortcode-ovima.
6. Periodično izvoziti podatke za arhivu.

## 23. Česti problemi i rešavanje

- Nema podataka na shortcodu:
  - proveriti kontekst (klub/liga/sezona),
  - proveriti da li su mečevi označeni kao odigrani,
  - proveriti slug vrednosti.
- Tabela prazna:
  - proveriti da li postoje mečevi za izabranu ligu/sezonu,
  - proveriti pravila takmičenja.
- Frontend ne prikazuje očekivani template:
  - proveriti theme override i fallback tok.

## 24. Verzije i održavanje

Pre svakog većeg update-a:
- backup,
- test na staging-u,
- smoke test ključnih shortcode stranica,
- provera import/export toka.

## 25. Bundled addon: Tournaments

OpenTT uključuje i turnirski addon u `addons/tournaments`.
Addon ima:
- sopstvenu šemu,
- sopstvene shortcode-ove,
- admin ekran i template tok.

Napomena o trenutnom statusu:
- Bundled tournaments addon je trenutno u razvoju i funkcionalno nije završen.
- Interfejsi, detalji modela podataka i release/pravni okvir za standalone upotrebu mogu se menjati do završne stabilizacije.
- Za produkcionu upotrebu kritičnih tokova preporučuje se validacija na staging okruženju pre puštanja.

Osnovni shortcode-ovi addona:
- `[opentt_tournaments]`
- `[opentt_tournament]`
- `[opentt_tournament_categories]`
- `[opentt_tournament_signup]`
- `[opentt_tournament_podium]`

## 26. Licenca

OpenTT je licenciran pod AGPL-3.0-or-later.
Ako se koristi kao servis (SaaS), primenjuju se AGPL obaveze objavljivanja izmena.

Dodatna pojašnjenja:
- Core kod plugina u ovom repozitorijumu distribuira se pod `AGPL-3.0-or-later` (vidi `LICENSE`).
- Bundled addon kod u `addons/tournaments` je deo istog repozitorijuma, ali je funkcionalno još u razvoju; politika i paketiranje za završni standalone release još nisu finalizovani.
- Licenca koda i upotreba brenda su odvojene teme: korišćenje naziva, logotipa i identiteta projekta uređeno je pravilima brenda/trademark-a. Pogledaj `trademark.md`.

## 27. Doprinosi i podrška

Doprinosi su dobrodošli kroz:
- prijavu grešaka,
- predloge poboljšanja,
- pull request-ove,
- fork-ove usklađene sa licencom.

---

Ako želite da uvedete standardizovan "club season archive" UX, preporuka je evolucija postojećih shortcode-ova uz jedinstven sezonski state, umesto jednog "mega" shortcode-a.
