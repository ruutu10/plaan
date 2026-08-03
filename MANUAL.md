# Plaan - kasutusjuhend

Plaan on Ruutu10 improteatri tehnikaplaneerimise süsteem. Esinevad trupid
kirjeldavad, mida nende lavastus valgus- ja helipuldilt vajab; tehnikatiim kogub
need kirjeldused kokku, kinnitab need ja juhib nende järgi õhtut.

See dokument kirjeldab, kuidas süsteem oma kasutajate jaoks käitub: mida saab
teha, mis järjekorras, mis toimub automaatselt ja kus on piirid.

Ekraanide ja nuppude nimed on tekstis toodud nii, nagu need liideses paistavad.

---

## 1. Kes süsteemi kasutavad

| Inimene | Mida ta teeb |
| --- | --- |
| **Esineja** | Täidab tehnikaplaani lavastusele, mida ta peagi mängib. Teavitus jõuab kohale tavaliselt e-kirja lingiga ja tal pole vaja süsteemi ülejäänud osa tundma õppida. |
| **Tiimi liige** | Kuulub ühte või mitmesse esinevasse truppi (tiimi). Hoiab korras tiimi lavastused, etenduste kuupäevad ja liikmeskonna. |
| **Tehnik** (roll `Tehnik`) | Viib etendused läbi. Loeb kõiki tehnikaplaane, kinnitab need ja hoiab korras kõik lavastused, etendused, tiimid ja kasutajakontod. |
| **Majarahvas** (roll `Ruutu10 tiim`) | Teatri e-posti aadressiga inimesed. Võivad lugeda kõiki esitatud plaane. |

Rollid antakse konto kaupa. Vaikimisi pole kellelgi ühtki rolli - välja arvatud
kaks automaatset teed, mida kirjeldab §2.4.

---

## 2. Sisse saamine

### 2.1 Neli ust

1. **Maagiline link (esineja uks).** Tehnikaplaani lehel kirjutad oma e-posti
   aadressi ja sulle saadetakse ühekordne sisselogimislink. Kui sellel aadressil
   kontot ei ole, luuakse lihtne konto kohapeal - täita pole ühtegi
   registreerimisvormi ja parooli ei pea valima.
2. **Meeldetuletuse link.** Meeldetuletuskirjad (§6) sisaldavad linki, mis logib
   sind sisse *ja* avab ühe klikiga vormi õige etenduse peal.
3. **Ruutu10 SSO (Authentik).** „Jätka Authentikuga“. Kui brauseris on Authentiku
   sessioon juba olemas, logib tehnikaplaani leht sind esimesel külastusel
   vaikselt sisse - jõuad kohale juba sisselogituna.
4. **E-post ja parool.** Tavaline registreerimine, sisselogimine, parooli
   taastamine, e-posti kinnitamine. Kaheastmeline autentimine
   (autentimisrakendus koos taastekoodidega) ja pääsuvõtmed on mõlemad saadaval
   Seaded → Turvalisus.

### 2.2 Kuidas lingid töötavad

- **Maagiline sisselogimislink** kehtib **30 minutit** ja seda saab järgida kuni
  **4 korda**.
- **Meeldetuletuse link** kehtib kuni **12 tundi pärast etendust**, millest ta
  räägib, ja seda saab järgida kuni **25 korda** - plaani kirjutamine käib harva
  ühe istumisega. Kuus päeva ette saadetud meeldetuletus kannab seega
  kuuepäevast linki; eelmisel õhtul saadetu lühiajalist.
- Maagilise lingi järgimine **kinnitab ühtlasi sinu e-posti aadressi** - oma
  postkastis lingile klõpsamine on sama tõend, mida kinnituskiri palub.

### 2.3 Kuhu sisselogimine su viib

Jõuad tagasi sinna, kust alustasid. Kui palusid sisselogimist jagatud plaani
lugedes, toob link su selle plaani juurde tagasi. Kui alustasid vormist, viib see
sind vormi juurde. Muul juhul jõuad oma töölauale.

### 2.4 Automaatselt antavad rollid

- Konto, mille **kinnitatud** aadress on ühel teatri enda e-posti domeenidest,
  võetakse automaatselt maja tiimi ja talle antakse **majarahva** roll. Aadress
  peab olema enne tõendatud: SSO tõendab selle otse, teised uksed e-posti
  kinnitamise kaudu.
- **Tehniku** rolli ei anta kunagi automaatselt. Selle jagab kätte teine tehnik
  kasutajahalduse ekraanil.

### 2.5 Piirangud

- Oma rolle muuta ei saa - seda peab tegema mõni teine tehnik. See on tahtlik:
  rolliekraani avav õigus on ise ühe rolli küljes, nii et enda muutmine laseks
  kellelgi end välja lukustada.
- E-posti aadressi muutmine (nii enda oma kui kellegi teise oma haldusekraanil)
  märgib selle uuesti **kinnitamata**.

---

## 3. Mille üle süsteem arvet peab

**Tiim (trupp)** - esinev trupp. Inimesed kuuluvad tiimidesse; tiimidele
kuuluvad lavastused.

**Lavastus** - produktsioon kui mõiste: selle nimi ja kirjeldus.

**Etendus** - lavastuse üks kuupäevaga mängimine, algusaja ja valikulise
kestusega. Kõik, mis on lavastuse mängimistel ühine, elab lavastuse küljes;
etendus hoiab ainult seda, mis võib erineda.

- Lavastusel, mida täidab üks trupp, on tiim *lavastuse* küljes ja iga etendus
  pärib selle.
- Õhtu, mida jagab mitu tiimi (Õppelava, gala), on **üks lavastus, mida
  mängitakse korra, ja iga etteaste on oma etendus** - igaüks oma tiimi ja oma
  pealkirjaga. Etteastet mängiv tiim on alati etteaste enda oma, kui see on
  määratud, ja muidu lavastuse oma.
- Kellaajad näidatakse alati teatri kella järgi (Europe/Tallinn). Kellaajata
  etendus algab vaikimisi kell 19:00.
- Etenduse võib märkida **mustandiks** (üle vaatamata). Mustandid on
  tehnikaplaani vormile nähtamatud ja nende pärast ei tagata kunagi puuduvat
  plaani - vt §7.3.

**Tehnikaplaan** - mida trupp ühe etenduse jaoks puldilt vajab. Igal plaanil on
püsiv jagamistunnus kujul `R10-2026-XXXXXXXXXXXX`.

### 3.1 Plaani staatused

| Staatus | Tähendus |
| --- | --- |
| **Mustand** | Salvestatud, aga üle andmata. Ikka veel esineja enda oma. |
| **Esitatud** | Antud tehnikatiimile üle. |
| **Tehniku kinnitatud** | Tehnik on plaani näinud ja kinnitanud, suuri küsimusi ega täpsustusi ei olnud. |
| **Arhiveeritud** | Etendus on ära mängitud. |

Esitatud ja kinnitatud plaanid on need, mille järgi tehnikatiim töötab - neid
loeb töölaud kokku ja need peatavad meeldetuletuskirjad. Arhiveeritud plaane ei
peideta: need on tehnikatiimi ülevaates endiselt näha ja neid pakutakse endiselt
sama lavastuse järgmise plaani lähtekohaks.

---

## 4. Tehnikaplaani kirjutamine

See on süsteemi peamine töövoog. Selleni jõuab aadressilt **`/tehnikaplaan`**,
külgmenüüst („Uus tehnikaplaan“) või otse meeldetuletuskirjast.

### 4.1 Samm 0 - enda tuvastamine

Plaani kirjutamiseks või salvestamiseks on vaja kontot. Külalisena vormi jõudes
küsitakse sinu e-posti aadressi ja sulle saadetakse sisselogimislink. Kui
SSO sessioon on juba olemas, jäetakse see samm vaikselt vahele.

Jagatud plaani lugemiseks kontot **ei** ole vaja (§4.5).

### 4.2 Seitse sammu

Vasakpoolne sammuriba näitab kõiki seitset; sammud 3 (Heli), 6 (Lisainfo) ja 7
(Ülevaade) on märgitud valikulisteks. Kui etendus on valitud, saad igal hetkel
hüpata ükskõik millisele sammule - miski muu ei nõua eelmise lõpetamist ja ükski
teine väli ei ole kohustuslik.

**1. Etendus - vali etendus.**
Sulle näidatakse kõiki maja tulevasi etendusi (kuni 100, lähim ees, mustandid
välja jäetud). Tänaõhtune etendus püsib nimekirjas kuni eesriide avanemiseni. Iga
plaan kuulub ühe etenduse juurde, nii et see on ainus valik, mille peale vorm
käib: sellest sammust edasi ei avane midagi enne, kui see on tehtud.

Kui sinu õhtut nimekirjas ei ole, vali **„Etendust pole nimekirjas“** -
asendusetendus, mida pakutakse nimekirja all punktiirkastina. Plaan jõuab
tehnikatiimini täpselt samamoodi nagu iga teine; kirjuta lavastuse nimi, kuupäev
ja kellaaeg viimase sammu Lisainfo lahtrisse ning tehnikatiim registreerib
etenduse ja tõstab plaani hiljem selle peale.

Valiku järel valid ka, **millest alustad**:
- tühi plaan või
- koopia plaanist, mis on juba esitatud sama lavastuse mõnele teisele etendusele.

Lavastuse kohta pakutakse kuni 5 varasemat plaani. Need ei ole ainult sinu enda
omad: arvesse läheb iga esitatud, kinnitatud või arhiveeritud plaan etendusele,
mida sinu tiimid mängivad, nii et lavastuse järgmise plaani võib kirjutada mõni
teine tiimi liige kui see, kes eelmise saatis. Kopeerimine teeb ka manustest ja
stseenide helifailidest uued koopiad - plaan, millest kopeerisid, jääb puutumata.

Valitud etenduse vahetamine lähtestab plaani sisu tühjaks - välja arvatud siis,
kui plaan on juba salvestatud, mispuhul see lihtsalt tõstetakse teisele õhtule,
sisu, failid ja link kaasa. Just nii tõstetakse asendusetenduse alla esitatud
plaan ümber, kui õige etendus on registreeritud.

**2. Standardinfo - mis kehtib alati.**
Ainult lugemiseks mõeldud ülevaade: kus tehnik istub, kuidas töötab etenduse aja
kell, kuidas etendus vaikimisi lõpeb, millal maja avatakse ja millal on tehniline
läbimäng ning mida tehnik võib omal algatusel teha. Samuti on seal link maja
tehnilise info juurde. Täita pole midagi - see samm on olemas selleks, et
esinejad teaksid, mida neil kirjeldada *ei* ole vaja.

**3. Heli - heliplaan.**
Kaks jah/ei-küsimust, millest kumbki avab „jah“ vastuse peale vaba tekstivälja:
mikrofonid (kogus, paigutus, kas töötav või rekvisiit - saadaval on kuni üks
juhtmevaba käsimikrofon) ja kas trupp toob kaasa oma muusiku (pill, kas ühendada
helisüsteemiga, toide ja kaabeldus, paigutus).

**4. Stseenid.**
Plaani tuum. Stseen on lavastuse sisuline või tehniline lõik, kus valgus- või
helilahendus muutub. Iga plaan algab kolme eeltäidetud stseeniga (sissetulek,
stseenid ise, väljaminek) ja vähemalt üks stseen peab alles jääma.

Igal stseenil on **nimi**, **valgus**, **heli** ja **märkused**. Valgusel ja helil
on ühe klikiga valmisnupud (nt „kiire blackout“, „üldvalgus“, „ruutu10 tunnus
3s“), mis lisatakse juba kirjutatu järele.

Stseeni heli antakse **kas** lingina **või** üleslaaditud failina - mitte kunagi
mõlemana; ühelt teisele vahetamine tühjendab teise. Üks helifail stseeni kohta ja
uus üleslaadimine asendab vana. Kui fail (või otsene helilink) on brauseris
mängitav, saab seda kohapeal kuulata.

Stseene saab lohistades ümber järjestada, dubleerida, kokku voltida ja kustutada.
Dubleeritud stseen ei võta algse stseeni helifaili kaasa.

**5. Erivahendid.**
Valikuline nimekiri esemetest (nimi + kuidas seda kasutatakse või millised on
piirangud) ning kaks maja küsimust: kas tehnik tohib kasutada suitsuefekte
(Improkeskuses endas see võimalik ei ole; vastus loeb üksnes mujal toimuvate
etenduste puhul) ja kas tehnik tohib teha omapoolseid stseeni mõjutavaid
pakkumisi - koos valikulise selgitusega, mis säilitatakse ka siis, kui vastus on
„ei“.

**6. Lisainfo - kõik muu.**
Vaba tekst ja failimanused.

**7. Ülevaade - vaata üle ja saada.**
Valmis plaan dokumendina. Siit saad:

- **Laadi alla PDF** - dokumendi printida või salvestada.
- **Avalik link** - plaani salvestada ja saada selle jagamislink, mis
  kopeeritakse lõikelauale.
- **AI ülevaatus** - küsida AI-tehnikult ülevaatust (§4.6).
- **Esita tehnikutiimile** - plaan salvestada ja esitada.
- Avada **tehniku mängimisvaate** - plaani keskendunud, stseenihaaval lugemise,
  mis on mõeldud puldi taha.

### 4.3 Salvestamine ja mustandid

- Kirjutamise ajal ei salvestata serverisse midagi. Sinu edenemine (sealhulgas
  see, millisel sammul olid) hoitakse **sinu brauseris** ja taastatakse, kui
  vormi samas brauseris uuesti avad.
- Plaan kirjutatakse serverisse esimest korda siis, kui teed avaliku lingi või
  esitad plaani. Seni on see olemas ainult kohapeal.
- Jagamislingiga avatud plaani ei hoita sinu brauseri kohaliku
  mustandina.
- Kui meeldetuletuse link nimetab etenduse ja sinu kohalik mustand on *mõne
  teise* etenduse oma, võidab link. Kui mustand on sellesama etenduse oma,
  taastatakse sinu mustand - see on töö, mille oled juba alustanud.
- Uuesti esitamine on tavaline: esitatud plaani saab uuesti avada, parandada ja
  uuesti esitada ning tehnikatiimi teavitatakse iga kord, nii et nende käes on
  alati kehtiv versioon.

### 4.4 Kes tohib plaani muuta

Sa võid plaani muuta, kui vähemalt üks järgnevast kehtib:

- sinu käes on selle jagamislink (nii annab autor muutmisõiguse edasi),
- sina kirjutasid selle,
- sinu tiim mängib etendust, mille kohta plaan käib (või tiimile kuulub lavastus,
  mille juurde etendus kuulub) - sealhulgas kolleegi lõpetamata mustandit, mis on
  just see, mida on vaja parandada,
- sa oled tehnik.

Plaani **autor ei muutu kunagi.** Sellise plaani salvestamine, mida sa ise ei
kirjutanud, omandit üle ei anna, ja sellised salvestused logitakse.

### 4.5 Avalik link

Igal salvestatud plaanil on püsiv link (`/tehnikaplaan/p/{tunnus}`), mis:

- avaneb **ilma kontota**, otse ülevaatelehel, ainult loetava dokumendina,
- sisaldab manuseid ja helifaile, mis samuti ilma sisselogimiseta avanevad,
- kutsub lugejat sisse logima, kui too soovib muuta - ja sisselogimislink toob ta
  tagasi täpselt selle plaani juurde.

Link ei aegu. Igaüks, kelle käes see on, saab plaani lugeda ja sisse logides ka
muuta.

### 4.6 AI ülevaatus

„AI ülevaatus“ saadab plaani sellisena, nagu see parajasti on, AI-ülevaatajale,
kes mängib kogenud majatehniku rolli, ja tagastab kirjalikud soovitused.

- See töötab salvestamata sisuga - saad ülevaatuse teha enne, kui üldse
  salvestad.
- See on üksnes nõuandev. Ekraan ütleb seda välja: soovitused ei ole kohustus.

AI tehnik on mõeldud lihtsate ja levinud puuduste kiireks tuvastamiseks.

### 4.7 Esitamine

Nupp **Esita tehnikutiimile** salvestab plaani, seab selle staatuseks
**Esitatud** ja märgib esitamise aja. Kohe seejärel:

- plaani autor saab e-postiga terve plaani kirjeldusena sellest, mida ta saatis,
- tehnikatiimi aadressile läheb sama dokument,
- plaan ilmub tehnikatiimi ülevaatesse ja peatab selle etenduse
  meeldetuletuskirjad.

E-postiga saadetud dokument, väljatrükk ja ekraanil olev ülevaade on üks ja sama
dokument, mis on koostatud samade reeglite järgi.

### 4.8 Täitmise piirid

- **Manused:** kuni 20 MB faili kohta. Lubatud tüübid: doc, docx, pdf, jpg, jpeg,
  png, gif, mp4, mov, avi, mkv, mp3, wav, ogg, qlc, txt, webp.
- **Stseeni helifailid:** ainult mp3, wav, ogg.
- Väljade pikkused: lavastuse kirjeldus 5000 märki; stseeni valgus/heli/märkused
  ja heli täpsustusväljad 2000 igaüks; erivahendi kasutus 1000; vaba tekst
  10 000; kestus 1–240 minutit.

---

## 5. Pärast plaani üleandmist - tehnikatiim

### 5.1 Plaanide ülevaade (`Saadetud plaanid`)

Saadaval kõigile, kes tohivad lugeda kõiki plaane (tehnikud ja majarahvas). See
loetleb **kõik maja plaanid, mustandid kaasa arvatud**, etenduse kuupäeva järgi,
kõige hilisem ees - tehnikatiim otsib seda, mis on tulemas või äsja ära
mängitud, mitte arhiivi. Asendusetenduse („Etendust pole nimekirjas“) alla
esitatud plaanid on dateeritud aastaid ette ja kogunevad seetõttu tippu, mis on
täpselt õige koht - just neile on vaja päris õhtut leida.

Iga rida avab detailivaate, kus on plaan ise, selle kirjutaja, etendus, mille
kohta see käib, ja esitamise aeg.

### 5.2 Plaani staatuse muutmine

Detailivaates saab tehnik viia plaani ükskõik millisesse staatusesse. Kõigi
plaanide lugemine ja nende staatuse muutmine on **eraldi õigused** - majarahvas
saab lugeda, aga mitte muuta.

Ainus üleminek, millel on tagajärg, on **Esitatud → Tehniku kinnitatud**: plaani
autorile saadetakse kiri, et tehnikatiim on selle üles võtnud. Plaanil, mille
autor on vahepeal eemaldatud, pole lihtsalt kellelegi teatada. Iga staatuse
muutus logitakse koos muutjaga.

---

## 6. Meeldetuletused

Esinejaid tagatakse automaatselt plaanide pärast, mida ei ole üle antud.

**Millal.** Kaks meeldetuletust etenduse kohta: **6 päeva** enne ja **30 tundi**
enne. Mõlemal on sama tekst - teine ei ole karmim kiri, vaid sama kiri, mis
saabub siis, kui unustamiseks enam aega ei ole.

**Kes need saavad.** Iga etendust mängiva tiimi liige - jagatud õhtul etteaste
enda tiim, muidu lavastuse tiim. Iga esineja saab oma lingi, sest link logib
hoidja sisse.

**Tehnikatiim** saab oma eraldi koopia, kus on kirjas, keda tagati. See koopia ei
sisalda meelega ühtki sisselogimislinki.

**Mis need peatab.** Selle etenduse esitatud või kinnitatud plaan. Mustand ei
loe - seda ei antud kunagi üle.

**Mille pärast kunagi ei tagata:**
- mustandiks märgitud etendused,
- etendused, millel pole tiimi (pole kellelegi kirjutada),
- etendused, mille tiimil pole liikmeid - see logitakse hoiatusena ja vaadatakse
  hiljem uuesti üle juhuks, kui keegi õigeks ajaks liitub,
- meeldetuletused, millest on juba üle sõidetud: etendus, mis registreeriti kolm
  päeva enne toimumist, ei saanud kunagi kuuepäevast akent, ja seisaku järel
  järele jõudev süsteem saadab ainult viimase tähtaja ületanud meeldetuletuse,
  mitte mõlemad korraga.

Iga meeldetuletus saadetakse etenduse kohta **täpselt üks kord**, mis ka
ajastajaga ei juhtuks. Kogu meeldetuletuste mehhanismi saab kogu majas välja
lülitada.

---

## 7. Lavastuste ja etenduste haldamine

### 7.1 Lavastused (`Lavastused`)

Selle ekraani saab avada iga sisseloginud kasutaja; loetletakse see, milleni sa
tohid ulatuda:

- lavastused, mis kuuluvad tiimile, kuhu sa kuulud, **ja**
- lavastused, mille mõnda etendust su tiim üksnes mängib (külalisesinemine
  kellegi teise õhtul).

Tehnikud näevad kõiki maja lavastusi, sealhulgas neid, millel pole omanikutiimi -
teisiti nendeni ei jõuagi.

Sa saad luua lavastuse (valides omaniku nende tiimide seast, kuhu ise kuulud;
tehnik võib valida ükskõik millise tiimi), selle ümber nimetada, kirjeldust muuta
ja anda selle teisele tiimile. Lavastust ei liigutata kunagi sinna, kuhu selle
muutja järele ei pääse.

**Kaks õigust on meelega erinevad.** Külalistrupp pääseb õhtule, mida ta mängib,
et parandada *oma* etendust, kuid lavastus ise - selle nimi, omanik,
kustutamine - jääb lavastuse tiimi kätte.

**Lavastuse kustutamine** paneb selle kõrvale (pehme kustutus) ja võtab etendused
kaasa, nii et miski ei jää osutama lavastusele, mida rakendus mujal enam ei
näita. Nendele etendustele kirjutatud plaanid säilitavad oma jälje.

### 7.2 Etendused

Neid hallatakse lavastuse muutmislehel. Etendusel on kuupäev ja algusaeg,
valikuliselt kestus, valikuliselt oma pealkiri ja oma esinev tiim (jagatud
õhtute jaoks) ning mustandi märge.

- **Etenduse lisamine** on ainuüksi lavastuse tiimi õigus - külalistrupp võib oma
  lõiku parandada, kuid mitte kavva enda omi juurde panna.
- **Etenduse muutmine ja kustutamine** on lubatud lavastuse tiimile, seda
  etendust mängivale tiimile ja tehnikutele.
- **Etenduse kustutamine ei kustuta sellele kirjutatud plaane.** Need osutavad
  sellele edasi - etendus on üksnes peidetud, nii et taastamine seob need kaks
  uuesti kokku - kuid seni loetakse neid plaanidena, millel pole lavastust, tiimi
  ega kuupäeva. Ekraan hoiatab, kui neid on; tagasitee on etendus taastada või
  plaan avada ja esimesest sammust mõne teise etenduse peale tõsta.

### 7.3 Mustandietendused

Mustandiks märgitud etendus on selline, mille automaatne import registreeris ja
mida keegi pole veel üle vaadanud - kuupäev võib olla vale või õhtut ei pruugi
üldse tulla. Kuni admin märke maha ei võta, on see:

- tehnikaplaani vormi etenduste valikust välja jäetud,
- puuduva plaani pärast kunagi tagamata.

Haldusekraanidel on see endiselt näha, üle vaatamata märkega.

### 7.4 Kogu maja etenduste ülevaade (`Etendused`)

Tehnikud saavad ühe nimekirja kõigist maja etendustest, uuemad ees, koos
sellega, mitu plaani igaühel on. Kõik teised jõuavad oma tiimide kuupäevadeni
Lavastuste kaudu.

---

## 8. Tiimid ja liikmeskond

### 8.1 Sinu enda tiimid (Seaded → Tiimid)

- Loo tiim. Looja saab selle omanikuks.
- Vaheta, millises tiimis sa parajasti töötad - töölaud ja külgmenüü järgivad
  seda.
- Kutsu inimesi e-postiga. **Kutsed aeguvad 3 päeva pärast** ja neid saab ootel
  olles tühistada. Aegunud kutsed koristatakse öösel ära.
- Võta kutseid vastu või lükka neid tagasi oma töölaual. Vastuvõtmine lülitab su
  sellesse tiimi. Kutse vastuvõtmine tiimi, kus sa juba oled, ei muuda midagi -
  senine roll jääb alles.
- Lahku tiimist (omanikud oma tiimist lahkuda ei saa). Kui lahkud, sind
  eemaldatakse või tiim kustutatakse, viiakse sind sellesse ülejäänud tiimidest,
  mis on tähestikus esimene. Kui ühtki ei jää, jääd ilma aktiivse tiimita ja
  ekraanid saavad sellega hakkama.
- Tiimi nimest saab selle URL-i osa, seega **tiimi ümber nimetamine muudab kõiki
  linke, mis seda nime sisaldavad**. Osa nimesid on reserveeritud ja neid ei
  lubata.

### 8.2 Rollid tiimi sees

| Roll | Saab |
| --- | --- |
| **Omanik** | Kõike: nimetada ümber, kustutada, liikmeid lisada ja eemaldada, liikmete rolle muuta, kutsuda, kutseid tühistada. |
| **Admin** | Tiimi ümber nimetada, kutsuda, kutseid tühistada. |
| **Liige** | Midagi halduslikku mitte. |

Omanikuks ei saa kedagi määrata - see roll kuulub tiimi loojale.

### 8.3 Kogu maja tiimide ülevaade (`Tiimid`)

Tehnikud haldavad siit kõiki maja tiime: loovad, nimetavad ümber, kustutavad ning
lisavad, muudavad rolli või eemaldavad liikmeid otse. Tavaline liige näeb seda
ekraani avades oma tiime ja talle öeldakse kohe ette, mida ta muuta ei saa -
selle asemel et seda keeldumise kaudu teada saada.

---

## 9. Kasutajakontod (`Kasutajad`)

Avatud ainult tehnikutele. Erinevalt lavastustest ja tiimidest ei sõltu siin
miski sellest, kuhu sa kuulud - kas näed kõiki maja kontosid või sind ei lasta
sisse.

Nimekiri näitab iga konto nime, aadressi, tema rolle ja seda, mitmesse tiimi ta
kuulub. Konto peal saad:

- parandada selle nime ja e-posti aadressi (mis märgib aadressi uuesti
  kinnitamata),
- rolle anda ja ära võtta.

Rolli andmine, mille oled juba andnud, või sellise ära võtmine, mida kontol ei
ole, ei muuda midagi - topeltklõpsatud lüliti on kahjutu. **Keegi ei tohi muuta
oma rolle.** Iga rollimuudatus logitakse koos muutjaga.

---

## 10. Töölaud

Sisselogimisjärgne avaleht. See asub sinu aktiivse tiimi all - tiimi vahetamine
viib sind ühelt töölaualt teisele -, kuid kokku loeb see kogu maja andmeid.

Kõik näevad:
- ootel kutseid tiimidesse koos ühe klikiga vastuvõtmisega,
- mitu etendust on kogu majas veel ees, millal on järgmine ja mitmel tulemas
  etendusel pole veel plaani.

Tehnikud ja majarahvas näevad lisaks **8 viimati esitatud plaani** ajajoont, kust
saab otse plaani sisse minna.

---

## 11. Mida süsteem ise teeb

| Töö | Millal | Mida ta teeb |
| --- | --- | --- |
| **Planka import** | Iga päev | Loeb tootmistahvli kaarte ja registreerib lavastused ja etendused, mida need kuulutavad. Uued etendused saabuvad **mustanditena**, mis ootavad ülevaatamist. Lavastusi, mille admin on siin kustutanud, ei äratata kunagi ellu. Kaarte saab sildi järgi välja jätta. |
| **Meeldetuletused** | Iga tund | Saadab välja iga tehnikaplaani meeldetuletuse, mille aeg on äsja kätte jõudnud (§6). Enamikul tundidel vaikne. |
| **Arhiveerimine** | Iga päev | Viib esitatud ja kinnitatud plaanid staatusesse **Arhiveeritud**, kui nende etendus mängiti ära rohkem kui 24 tundi tagasi. Esineja enda mustandit ei arhiveerita kunagi - seda ei antud kunagi üle. |
| **Kutsete koristus** | Iga päev | Kustutab aegunud tiimikutsed. |
| **Üleslaadimiste koristus** | Kord nädalas | Kustutab üle 72 tunni vanused ootel failid, mis ei jõudnud ühegi plaani külge. |

### 11.1 Kuidas import kaarti loeb

Kaart kuulutab ühe või mitu õhtut ja õhtu ühe või mitu etteastet. Õhtust, mille
täidab üks trupp, saab korra mängitav lavastus; Õppelavast saab üks korra
mängitav lavastus, millel on iga tiimi kohta üks etendus. Sobitamine käib nime
(lavastus), lavastuse + kuupäeva (õhtu) ja õhtusisese nime (etteaste) järgi, nii
et sama kaardi uuesti importimine ei lisa midagi.

Kuna lugemist teeb AI, **säilitatakse iga imporditud kirje taga olev
arutluskäik** ning tehnikud ja majarahvas saavad selle lavastuse ja etenduse
ekraanidelt lahti teha - nii jõuab vale kuupäev tagasi kaardini, kust see tuli.
Kirjed näitavad, kas need sisestati käsitsi või impordiga.

---

## 12. Kes mida näeb - kokkuvõte

| | Oma plaanid | Oma tiimi plaanid | Kõik plaanid | Plaani staatuse muutmine | Oma tiimi lavastused | Kõik lavastused / etendused / tiimid | Kasutajakontod ja rollid |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Esineja (rollita) | ✅ | ✅ | - | - | ✅ | - | - |
| Majarahvas | ✅ | ✅ | ✅ lugeda | - | ✅ | - | - |
| Tehnik | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

Igaüks, kelle käes on plaani **jagamislink**, saab seda plaani ilma kontota
lugeda ja sisse logides ka muuta.

Veel kaks märkust piiride kohta:

- Kõigi plaanide lugemine ja nende staatuse muutmine on eraldi õigused:
  majarahvas saab lugeda, aga mitte kinnitada.
- Kolleegi *lõpetamata mustandit* saab tema tiim muuta (selleks mustand ongi),
  kuid seda ei pakuta kunagi uue plaani lähtekohaks - selleks pakutakse ainult
  esitatud, kinnitatud ja arhiveeritud plaane.

---

## 13. Mis inimesi tavaliselt üllatab

- **Vormis ei ole miski kohustuslik.** Esitada saab ka plaani, mille iga väli on
  tühi. Standardinfo samm on olemas selleks, et esinejad teaksid, mis juhtub, kui
  nad midagi ei ütle.
- **Plaani ei salvestata enne, kui teed avaliku lingi või esitad selle.** Enne
  seda elab see ainult sinu brauseris.
- **Jagamislink ongi muutmisõigus.** Selle saatmine kellelegi, kes on sisse
  loginud, annab talle võimaluse plaani muuta.
- **Uuesti esitamine on ootuspärane.** Tehnikatiimi teavitatakse iga kord uuesti.
- **Vana kirja meeldetuletuse link võib avada tühja vormi.** Nii juhtub siis, kui
  etendus, mille see nimetas, on vahepeal ära mängitud või mustandiks tagasi
  pandud; vorm lihtsalt avaneb algusest koos valikunimekirjaga.
- **Tiimi ümber nimetamine lõhub lingid, mis selle nime sisaldavad.**
- **Etenduse kustutamine jätab selle plaanid alles**; lavastuse kustutamine võtab
  selle etendused kaasa.
- **Majarahva rolli eemaldamine teatri aadressilt ei jää püsima** - see antakse
  uuesti, kui aadress järgmine kord tõendatakse.
