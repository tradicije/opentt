# LibreTT Pravila Korišćenja Imena i Brenda

## 1. Svrha

LibreTT je slobodan i otvoren softver namenjen digitalizaciji i dugoročnom očuvanju infrastrukture stonog tenisa.
Izvorni kod je licenciran pod GNU AGPL uslovima, koji daju široka prava korišćenja, proučavanja, izmena i deljenja softvera.

Ovaj dokument reguliše posebnu temu: korišćenje LibreTT naziva, logotipa i identiteta projekta.

Cilj pravila nije ograničavanje zajednice, već zaštita integriteta projekta, poverenja korisnika i jasnog razlikovanja šta je zvanično.

## 2. Opseg

Ova politika se odnosi na:
- naziv `LibreTT`,
- LibreTT logotipe i vizuelne oznake,
- zvanični identitet i predstavljanje projekta,
- tvrdnje koje impliciraju zvaničnu povezanost, odobrenje ili upravljanje.

Ova pravila ne ograničavaju AGPL slobode nad izvornim kodom. Sloboda koda i pravila brenda su odvojeni slojevi.

## 3. Prava nad kodom (Licenca koda)

Pod AGPL uslovima dozvoljeno je:
- korišćenje LibreTT koda,
- proučavanje i verifikacija LibreTT koda,
- izmena LibreTT koda,
- pokretanje sopstvenih LibreTT instanci,
- forkovanje i redistribucija uz poštovanje AGPL obaveza.

Ako nudite modifikovani LibreTT kao mrežni servis, primenjuju se AGPL obaveze deljenja izvornog koda.

## 4. Pravila brenda i identiteta

Čak i kada je upotreba koda dozvoljena, LibreTT brend se ne sme koristiti na način koji može dovesti korisnike u zabludu.

### 4.1 Dozvoljeno bez prethodne dozvole

- tačno navođenje LibreTT-a kao izvornog (upstream) projekta,
- činjenične izjave tipa "zasnovano na LibreTT" ili "fork LibreTT-a",
- linkovanje ka zvaničnom repozitorijumu i dokumentaciji,
- javna diskusija kompatibilnosti sa LibreTT-om,
- doprinosi zajednici uz tačno navođenje izvora.

### 4.2 Nije dozvoljeno bez prethodne dozvole

- predstavljanje nezvanične verzije/forka kao zvaničnog LibreTT projekta,
- korišćenje LibreTT imena/logotipa na način koji zbunjuje korisnike o autorstvu ili odobrenju,
- lažno predstavljanje partnerstva, sertifikacije ili zvaničnog odobrenja,
- imitiranje zvanične infrastrukture, release kanala ili support identiteta,
- korišćenje LibreTT brenda kao primarnog identiteta divergentnog forka bez jasnog razlikovanja.

## 5. Obavezna transparentnost za forkove i derivate

Ako objavljujete fork ili derivatnu distribuciju, preporučeno je da:
- jasno navedete da je nezvanična verzija (npr. "Nezvanični fork LibreTT-a"),
- vidljivo uputite na upstream LibreTT projekat,
- izbegnete naziv/branding koji implicira da je vaša verzija kanonski upstream.

Preporučene formulacije:
- "Ovaj projekat je nezavisni fork zasnovan na LibreTT-u."
- "Nije zvanično povezan sa LibreTT projektom niti od njega odobren."

## 6. Smernice za domene, društvene naloge i distribuciju

Radi smanjenja konfuzije korisnika, izbegavati:
- domene koji deluju kao zvanična LibreTT infrastruktura, osim ako ste zvanični maintainer,
- social handle-ove ili package nazive koji sugerišu kanonsko vlasništvo.

Preporuka je eksplicitan naziv derivata, na primer:
- `<vas-org>-opentt`,
- `opentt-<region>-fork`,
- `<ime-proizvoda> (zasnovano na LibreTT)`. 

## 7. Upotreba logotipa i vizuelnog identiteta

Zvanični LibreTT logotipi/oznake ne treba da budu primarni identitet modifikovane distribucije bez dozvole.
Ako se prikazuje kompatibilnost ili poreklo, upotreba logotipa mora biti sekundarna i jasno kontekstualna.

Ako postoji nedoumica, koristiti tekstualnu atribuciju umesto logotipa.

## 8. Zvanična i nezvanična komunikacija

Samo zvanični kanali projekta mogu objavljivati poruke kao "LibreTT official".
Maintaineri forkova i integratori treba da komuniciraju pod sopstvenim imenom i identitetom.

Korisniku mora biti jasno:
- ko upravlja softverom/uslugom koju koristi,
- da li je u pitanju zvanični LibreTT ili derivat treće strane.

## 9. Zašto ova pravila postoje

Ova pravila postoje da štite:
- poverenje u podatke i operativnu pouzdanost,
- korisnike od nenamerne zabune,
- poverenje zajednice u upstream izdanja,
- dugoročnu održivost otvorenog, javno korisnog projekta.

Cilj je očuvanje otvorenosti uz sprečavanje zloupotrebe identiteta.

## 10. In-progress moduli i predstavljanje brenda

Neki bundled moduli (na primer `addons/tournaments`) mogu biti u razvoju i funkcionalno nedovršeni.
Takve module ne treba predstavljati kao finalizovane zvanične LibreTT podsisteme, osim ako maintainers to eksplicitno objave.

Pri distribuciji takvih modula, potrebno je jasno komunicirati nivo zrelosti i release status.

## 11. Traženje dozvole

Ako želite da koristite LibreTT ime/logo na način koji nije jasno obuhvaćen ovim pravilima, tražite dozvolu maintainera pre objavljivanja.

Zahtev treba da sadrži:
- kontekst upotrebe,
- tačan predlog naziva/brandinga,
- kanale distribucije,
- da li je build zvaničan, derivatan ili komercijalna podrška oko upstream-a.

## 12. Evolucija politike

Ova politika može se unapređivati kako projekat raste.
Maintaineri mogu dopunjavati primere i rubne slučajeve radi praktične i fer primene.

## 13. Sažetak

LibreTT kod je otvoren i forkabilan pod AGPL uslovima.
LibreTT identitet (naziv/logo/zvanično predstavljanje) ne sme se koristiti na obmanjujući način.

Korišćenje, razvoj i deljenje su podstaknuti, uz obaveznu transparentnost o tome ko upravlja kojom verzijom.
