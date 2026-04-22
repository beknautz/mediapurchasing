-- Vendor import from vendorList.xlsx
-- Generated automatically — review before running
-- Run against the mediapurchasing database

SET NAMES utf8mb4;

INSERT INTO vendors
  (company_name, contact_name, email, billing_email, phone,
   address, city, state, zip, category, notes, status,
   created_by, created_at, updated_at)
VALUES
  ('KIMA Yakima/Tri-Cities', 'Steve Crow', 'scrow@kimatv.com', NULL, 'Office: 509.895.8016 Cell: 509.961.0970',
   '2801 Terrace Heights Dr. Yakima, WA 98901', '2801 Terrace Heights Dr. Yakima', 'WA', '98901',
   'TV - English', 'Market: Yakima
Sherry Fischer | sfischer@keprtv.com | 509.416.5774
John Kennedy O''Connor | jkoconnor@kimatv.com | 509.895.8030
Austin Peppers | acpeppers@kimatv.com | 509.833.9594
Jeremy Loyd | jloyd@kimatv.com', 'active',
   1, NOW(), NOW()),
  ('KEPR', 'Ryan Rogers - Sports', NULL, NULL, NULL,
   NULL, NULL, NULL, NULL,
   'TV - English', 'Market: Yakima', 'active',
   1, NOW(), NOW()),
  ('KAPP AppleValley News', NULL, 'jloftus@applevalleynewsnow.com', NULL, NULL,
   '402 E Yakima Ave Suite 100, Yakima WA 98901', '402 E Yakima Ave Suite 100, Yakima', 'WA', '98901',
   'TV - English', 'Market: Yakima
Press Releases | news@applevalleynewsnow.com | Office: 509.735.8369
Jessica Serrano | jserrano@applevalleynewsnow.com | Cell: 509.378.5583
Eric Wencl | ewencl@applevalleynewsnow.com | 509.581.6138. Cell 509.242.5452', 'active',
   1, NOW(), NOW()),
  ('KVEW', 'Ausitn Reed', 'areed@applevalleynewsnow.com', NULL, '541-390-8842',
   NULL, NULL, NULL, NULL,
   'TV - English', 'Market: Yakima', 'active',
   1, NOW(), NOW()),
  ('KNDO/KNDU', 'Sam Renner', 'sam.renner@nonstoplocal.com', NULL, 'Office: 509.225.2310 Cell: 509.759.2127',
   '216 W. Yakima Ave, Yakima Wa 98902', '3312 W. Kennewick Ave, Kennewick', 'WA', '99336',
   'TV - English', 'Market: Yakima
Trude Smith | trude.smith@nonstoplocal.com | Office: 509.225.2310  Cell: 509.851.9999
Camille Kellison | camille.kellison@nonstoplocal.com
Christopher Wright | christopher.wright@nonstoplocal.com
Office: 509-225-2323 Cell:714-809-1048
Cameron Derrick | cameron.derrick@kndu.com | Yakima Office: 509.225.2302 TriCities 509.737.6708 Cell 509.528.5366
Sophia Lesseos | sophia.lesseos@nbcrightnow.com
news@kndu.com | Press Releases News desk 509.737.6725
Matt Elias | matt.elias@nonstoplocal.com | 425.791.2089
Eli Kern | eli.kern@nonstoplocal.com', 'active',
   1, NOW(), NOW()),
  ('FOX', 'Ryan Messer', 'ryan.messer@kcyutv.com', NULL, 'Cell: 509.961.2165 Direct: 509.490.3086 Office: 509.574.4141',
   '1205 W Lincoln Ave - Yakima, WA', NULL, NULL, NULL,
   'TV - English', 'Market: Yakima
Glenn Rausch | glenn.rausch@kffxtv.com | 509-735-1700 Station Manager KCYU/KFFX & Telemundo
Raquel Dudley | raquel.dudley@kayutv.com', 'active',
   1, NOW(), NOW()),
  ('Univision', NULL, NULL, NULL, '509.457.4886',
   '2801 Terrace Heights Dr. Yakima, WA 98901', '2801 Terrace Heights Dr. Yakima', 'WA', '98901',
   'TV - Spanish', 'Market: Yakima
509.952.7123 Reporter', 'active',
   1, NOW(), NOW()),
  ('Hispanavision - Yakima County', 'Orson Bevins', 'orson.bevins@hispanavisiontv.com', NULL, '509.452.8817',
   '715 W Yakima Ave, Yakima, WA 98902', '715 W Yakima Ave, Yakima', 'WA', '98902',
   'TV - Spanish', 'Market: Yakima
Luis Cardenas | luis.cardenas@hispanavisiontv.com | 512.627.3877
Whitney maxwell | whitney@hispanavisiontv.com | Cell 509.571.6344', 'active',
   1, NOW(), NOW()),
  ('Townsquare - Yakima & Tri-Cites', 'Nicole Cook', 'nicole.cook@townsquaremedia.com', NULL, 'Cell: 509.823.3115 Office: 509.834.4142',
   '4010 Summitview Ave, Yakima, WA 98908', '4010 Summitview Ave, Yakima', 'WA', '98908',
   'Radio - English', 'Market: Yakima
Brian Stephenson | brian.stephenson@townsquaremedia.com | 509.388.6384
Reesha | reesha.cosby@townsquaremedia.com
Lance Tormey | lance.tormey@townsquaremedia.com
Jessi Carolus | jessi.carolus@townsquaremedia.com | Cell: 206.226.4400 Desk: 509. 834.4105
D-Rez
John Riggs | john.riggs@townsquaremedia.com | 509.596.8814
Timmy Hubert | timmy@townsquaremedia.com | 509.969.8812
Jack Balzer | 509.833.2050
Paul Drake | paul.drake@townsquaremedia.com | Office: 509.547.9791 Cell: 509.727.3335', 'active',
   1, NOW(), NOW()),
  ('Stephens Media Group - Yakima', 'Laurie Hammermeister', 'laurie.hammermeister@smgnational.com', NULL, 'Office: 509.248.2900  Cell: 509.728.8468',
   '17 N 3rd St. Suite 103 Yakima, WA  98901', '17 N 3rd St. Suite 103 Yakima', 'WA', '98901',
   'Radio - English', 'Market: Yakima
Alicia Aguirre | alicia.aguirre@smgnational.com
Justin Henriksen | justin.henriksen@smgnational.com | 509.249.9606
Todd Lyons | todd.lyons@smgnational.com | Cell: 509.388.4035
Steve Rocha | steve.rocha@smgnational.com | Cell: 509.823.6674
Bryant Konold | bryant.konold@smgnational.com', 'active',
   1, NOW(), NOW()),
  ('Stephens Media Group - Tri-Cities', 'Dan Manella', 'dan.manella@smgnational.com', NULL, 'Office 509.783.0783',
   '4304 W 24th Ave Suite 200', 'Kennewick', 'WA', '99338',
   'Radio - English', 'Market: Yakima', 'active',
   1, NOW(), NOW()),
  ('KXLE Radio Ellensburg', 'Claudia Self', 'cpself51@gmail.com', NULL, NULL,
   '1311 Vantage Highway. Ellensburg 98926', NULL, NULL, NULL,
   'Radio - English', 'Market: Yakima
Bill Wolfenbarger | bill.wolfenbarger@jodesha.com
Jenae Jobe - Aberdeen', 'active',
   1, NOW(), NOW()),
  ('Cherry Creek Radio - Wenatchee', 'Michaella Collins', 'michaella.collins@townsquaremedia.com', NULL, 'Direct: 509.888.8428 Cell: 509.393.2326',
   NULL, NULL, NULL, NULL,
   'Radio - English', 'Market: Yakima', 'active',
   1, NOW(), NOW()),
  ('Townsquare - KPQ - Wenatchee', NULL, NULL, NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Radio - English', 'Market: Yakima', 'active',
   1, NOW(), NOW()),
  ('Bustos Media', 'Ruben Prieto', 'rprieto@bustosmedia.com', NULL, 'Cell: 509.945.7123',
   '415 N 2nd st, Yakima WA 98901', '415 N 2nd st, Yakima', 'WA', '98901',
   'Radio - Spanish', 'Market: Yakima
Humberto Salina | hsalinas@bustosmedia.com | Cell 530.844-4491
Jesenia Lopez | jlopez@bustosmedia.com | 509.457.1000', 'active',
   1, NOW(), NOW()),
  ('Radio KDNA', 'Francisco Rios', 'frios@kdna.org', NULL, 'Office: 509.854.2222 ext. 107',
   'PO BOX 800 Granger 98932', NULL, NULL, NULL,
   'Radio - Spanish', 'Market: Yakima
Carolina Montes | cmontes@kdna.org | Office: 509.854.2222 ext. 108
Elizabeth Torres | etorres@kdna.org | Office: 509.854.2222 ext. 102', 'active',
   1, NOW(), NOW()),
  ('Diamante digital marketing', NULL, 'gaudencio@diamantedigitalmedia.com', NULL, 'Office: 509.579.0102',
   '207 N. Dennis St.', NULL, NULL, NULL,
   'Radio - Spanish', 'Market: Yakima
Gaudencio Felipe | Cell: 509. 855. 6864', 'active',
   1, NOW(), NOW()),
  ('Sunnyside Daily Record - Lower Valley', 'Ileana Martinez', 'imartinez@sunnysidesun.com', NULL, '509.837.4500 ext.115',
   'Po Box 878 Sunnyside, 98944', NULL, NULL, NULL,
   'Newspaper', 'Market: Yakima', 'active',
   1, NOW(), NOW()),
  ('Ellensburg Daily record', NULL, 'advertising@kvnews.com', NULL, 'D: 509.204.8239 Cell: 360.689.9409 O: 509.925.1414 ext. 570233',
   '401 N Main Street, Ellensburg, WA 98926', '401 N Main Street, Ellensburg', 'WA', '98926',
   'Newspaper', 'Market: Yakima
Josh Crawford | jcrawford@kvnews.com
Amanda | abequette@kvnews.com
Sabrina | snutt@kvnews.com', 'active',
   1, NOW(), NOW()),
  ('Toppenish review, selah journal', 'Adam Smith', 'asmith3421@hotmail.com', NULL, 'Office: 509.823.4580',
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Yakima
Cell: 509-859-6254', 'active',
   1, NOW(), NOW()),
  ('Yakima Herald', 'Yesenia pastrana', 'ypastrana@yakimaherald.com', NULL, 'Office: 509.248.1251 Direct: 509.577.7705',
   '114 N. 4th Street - Yakima, WA 98901', '114 N. 4th Street - Yakima', 'WA', '98901',
   'Newspaper', 'Market: Yakima
509.577.7676
Sara Rae Shields | sshields@yakimaherald.com | writer 509.577.7693
Greg Thompson | gthompson@yakimaherald.com | 509.577.7668
news@yakimaherald.com
Joanna Markell | jmarkell@yakimaherald.com
Tammy Ayer | tayer@yakimaherald.com | Office: 509-759-7898', 'active',
   1, NOW(), NOW()),
  ('El sol de Yakima', 'Gloria Ibañez', 'gibanez@yakimaherald.com', NULL, '509.249.6184',
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Yakima', 'active',
   1, NOW(), NOW()),
  ('Grandview Herald & Prosser Record Bulletin', 'Rebecca Fink', 'salesrep@therecordbulletin.com', NULL, NULL,
   'P.O. Box 750, Prosser, WA 99350', 'P.O. Box 750, Prosser', 'WA', '99350',
   'Newspaper', 'Market: Yakima
Trudy | Grandview - 509.882.3712  Prosser - 509.786.1711', 'active',
   1, NOW(), NOW()),
  ('Business Times', 'David Gonzales', 'dgonzales@yvpub.com', NULL, 'Office: 509.457.4886',
   '416 S 3rd Street - Yakima', NULL, NULL, NULL,
   'Newspaper', 'Market: Yakima
Bob Kirkpatrick | bkirkpatrick@yvpub.com
Bruce Smith | bsmith@yvpub.com | Office: 509.457.4886
Emily Goodell | egoodell@yvpub.com', 'active',
   1, NOW(), NOW()),
  ('Yakima Valley Tourism', 'Jennifer Martinkus', 'jennifer@visityakima.com', NULL, 'D:509.573.3384 Office: 509.575.3010',
   '10 North 8th Street, Yakima WA 98901', '10 North 8th Street, Yakima', 'WA', '98901',
   'Newspaper', 'Market: Yakima
Katie | katie@visityakima.com | 509.575.3010
Adam Stuart - Media outreach | adams@visityakima.com
Adam Jones - visitor center | adams@visityakima.com', 'active',
   1, NOW(), NOW()),
  ('La Voz newspaper Yak/TR', NULL, 'lavozdeyuma@gmail.com', NULL, NULL,
   'P.O. Box 1023, Pasco, WA 99301', 'P.O. Box 1023, Pasco', 'WA', '99301',
   'Newspaper', 'Market: Yakima
David Cortinas | Cell: 509.539.2753
Marty Ruiz-Cortinas | Cell: 509.539-2752
lavoz@bmi.net | Office: 509.545.3055
lavoznewspaper@gmail.com', 'active',
   1, NOW(), NOW()),
  ('The Entertainer (TriCities) Print', NULL, 'deborah@theentertainernewspaper.com', NULL, NULL,
   '2724 Kyle Rd. Kennewick, WA 99338', '2724 Kyle Rd. Kennewick', 'WA', '99338',
   'Newspaper', 'Market: Yakima
Debbie Ross | Cell: 509.366.6206 Office: 509.734.1186', 'active',
   1, NOW(), NOW()),
  ('North Sound Media', 'Joe Dinneen', 'joe.dinneen@northsoundteam.com', NULL, 'O: 425.304.1381 ext 110 C: 907.830.0963 Alt: 425.622.9266',
   '2707 Colby Avenue Suite 1380', 'Everett', 'WA', '98201',
   'Radio - English', 'Market: Monroe
Elise Detloff | elise.detloff@northsoundteam.com', 'active',
   1, NOW(), NOW()),
  ('Audacy Seattle', 'April Berube', 'april.berube@audacy.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Radio - English', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Hubbard Radio', 'Catie Beck', 'catiebeck@hbi.com', NULL, 'C: 425.941.9063',
   '3650 131st Ave SE, #550', 'Bellevue', 'WA', '98006',
   'Radio - English', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Bustos Media', 'Press Release', 'info@bustosmedia.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Radio - Spanish', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('KSVR', 'Press Release', 'info@ksvr.org', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Radio - Spanish', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Radio Amor', 'Press Release', 'radioamor308@gmail.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Radio - Spanish', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Everett Herald - Sound Publishing', 'Colleen Bagdon', 'colleen.bagdon@heraldnet.com', NULL, '425.339.3034 ext 31620',
   '1800 41st Street S-300', 'Everett', 'WA', '98203',
   'Newspaper', 'Market: Monroe
Carrie Radcliff | carrie.radcliff@heraldnet.com | 425.339.3052 ext 30020', 'active',
   1, NOW(), NOW()),
  ('Snohomish Tribune', 'Becky Reed', 'becky@snoho.com', NULL, 'O: 360.568.4121 ext 201',
   'P.O. Box 499', 'Snohomish', 'WA', '98291',
   'Newspaper', 'Market: Monroe
Michelle Ewing | michelletribune@snoho.com | O:360.568.4121 Ext 203
letters@snoho.com', 'active',
   1, NOW(), NOW()),
  ('Everett Post', 'Press Release', 'editor@everettpost.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Monroe
Joe Dineen', 'active',
   1, NOW(), NOW()),
  ('Skagit Valley Herald', 'Newsroom', 'news@skagitpublishing.com', NULL, '360.424.3251 TF: 1.800.683.3300',
   '1215 Anderson Road', 'Mount Vernon', 'WA', '98274',
   'Newspaper', 'Market: Monroe
Advertising | ads@skagitads.com
Classifieds | classified@skagitpublishing.com', 'active',
   1, NOW(), NOW()),
  ('Seattle Daily Journal of Commerce', 'Press Releases', 'editor@djc.com', NULL, 'O: 206.622.8272 F: 206.622.8416',
   '83 Columbia Street Suite 200', 'Seattle', 'WA', '98104',
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Seattle Times', 'Lindsay Taylor', 'ltaylor@seattletimes.com', NULL, '206.652.6700',
   'P.O. Box 70', 'Seattle', 'WA', '98111',
   'Newspaper', 'Market: Monroe
Press Releases | newstips@seattletimes.com | O: 206.624.7323 TF: 1.888.624.7323', 'active',
   1, NOW(), NOW()),
  ('Edmonds Beacon', 'Advertising', 'edmondssales@yourbeacon.net', NULL, '425.347.5634',
   '728 3rd St. Suite D', 'Mukilteo', 'WA', '98275',
   'Newspaper', 'Market: Monroe
Press Releases | edmondseditor@yourbeacon.net', 'active',
   1, NOW(), NOW()),
  ('Issaquah Sammamish Reporter - Sound Publishing', 'William Shaw', 'william.shaw@soundpublishing.com', NULL, NULL,
   '909 S. 336th Street Suite 105', 'Federal Way', 'WA', '98003',
   'Newspaper', 'Market: Monroe
Press Releases | editor@issaquahreporter.com | 425.391.0363
Advertising', 'active',
   1, NOW(), NOW()),
  ('Kent Reporter - Sound Publishing', 'Marie Skoor', 'marie.skoor@kentreporter.com', NULL, NULL,
   '909 S. 336th Street Suite 105', 'Federal Way', 'WA', '98003',
   'Newspaper', 'Market: Monroe
Press Releases | andy.hobbs@soundpublishing.com | 253.872.6600
Advertising', 'active',
   1, NOW(), NOW()),
  ('Lynnwood Times', 'Press Releases', 'editorial@lynnwoodtimes.com', NULL, '425.931.1374',
   '12918 Mukilteo SPDWY C23, PMB-162', 'Lynnwood', 'WA', '98087',
   'Newspaper', 'Market: Monroe
Advertising | sales@lynnwoodtimes.com | 425.308.8371', 'active',
   1, NOW(), NOW()),
  ('Mercer Island Reporter', 'Katherine Miller', 'katherine.miller@mi-reporter.com', NULL, NULL,
   '11630 Slater Ave. N.E. Suite 8/9', 'Kirkland', 'WA', '98034',
   'Newspaper', 'Market: Monroe
Press Releases | andy.hobbs@soundpublishing.com | 206.232.1215
Advertising', 'active',
   1, NOW(), NOW()),
  ('Mill Creek Beacon', NULL, NULL, NULL, NULL,
   'https://www.millcreekbeacon.com/forms/report-news/', NULL, NULL, NULL,
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Renton Reporter', 'Press Release', 'andy.hobbs@soundpublishing.com', NULL, NULL,
   '909 S. 336th Street Suite 105', 'Federal Way', 'WA', '98003',
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('The Facts Newpaper', 'Press Release', 'seattlefacts@yahoo.com', NULL, '206.324.0552',
   '1112 34th Ave', 'Seattle', 'WA', '98122',
   'Newspaper', 'Market: Monroe
Advertising', 'active',
   1, NOW(), NOW()),
  ('Madison Park Times', 'Christina Hill', 'ppcadmanager@pacificpublishingcompany.com', NULL, '206.461.1300',
   '636 South Alaska Street, Suite E2', 'Seattle', 'WA', '98108',
   'Newspaper', 'Market: Monroe
Advertising
Press Release | mptimes@pacificpublishingcompany.com', 'active',
   1, NOW(), NOW()),
  ('Seattle Medium', 'Press Release', 'info@seattlemedium.com', NULL, '206.323.3070',
   '2600 S Jackson St', 'Seattle', 'WA', '98144',
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Bellevue Reporter', NULL, NULL, NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Covington Reporter', NULL, NULL, NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Kirkland Reporter', NULL, NULL, NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Seattle Weekly', NULL, NULL, NULL, '206.623.0500',
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Television', NULL, 'eveningtips@king5.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Monroe
Press Release | tips@komonews.com | 206.404.4000
newstips@kiro7.com
editor@cascadepbs.org.
fox13tips@fox.com', 'active',
   1, NOW(), NOW()),
  ('Radio', NULL, NULL, NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Monroe', 'active',
   1, NOW(), NOW()),
  ('Alpha Media', 'Carol Arevalo - KWIQ', 'carol.arevalo@alphamediausa.com', NULL, 'O: 509-765-1761 ext 14. C: 509-760-2702',
   NULL, NULL, NULL, NULL,
   'Radio - English', 'Market: Coulee City
Todd Farrar | todd.farrar@alphamediausa.com | office: 509-663-5186 Cell: 509-293-3268
Other Stations are Different Contacts: KKRV, ESPN, KWLN', 'active',
   1, NOW(), NOW()),
  ('State of Washington Tourism', 'Michelle McKenzie', 'michelle@stateofwatourism.com', NULL, NULL,
   'www.stateofwatourism.com', 'Seattle', 'WA', '98116',
   'Newspaper', 'Market: Coulee City
Mike Moe | mike@stateofwatourism.com | C: 425-444-0589
Director
Marianne Graff | marianne@stateofwatourism.com', 'active',
   1, NOW(), NOW()),
  ('News Standard', 'ShirleyRae Maes', 'tns@accima.com', NULL, '509-681-0014',
   'PO Box 488', 'Coulee City', 'WA', '98115',
   'Newspaper', 'Market: Coulee City
Editor/Publisher/Owner | sraemaes@gmail.com
Newspaper only, No Digital advertising', 'active',
   1, NOW(), NOW()),
  ('Wenatchee World', NULL, NULL, NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Coulee City', 'active',
   1, NOW(), NOW()),
  ('Grant County Tourism', 'Rachelle Baughman', 'rachelle@couleecreativeco.com', NULL, '509-362-9736',
   'couleecreativeco.com', NULL, NULL, NULL,
   'Newspaper', 'Market: Coulee City
All advertising for magazine due December for following year', 'active',
   1, NOW(), NOW()),
  ('Star Newspaper', 'Gwen Hilson', 'gwen@grandcoulee.com', NULL, '509-633-1350',
   'Po Box 150', 'Grand Coulee', 'WA', '99133',
   'Newspaper', 'Market: Coulee City
General mailbox | star@grandcoulee.com', 'active',
   1, NOW(), NOW()),
  ('KOHD/KBNZ', 'Diane Wozniak', 'dwozniak@zolomedia.com', NULL, 'Office: 541.876.6754',
   '63090 Sherman Rd', NULL, NULL, NULL,
   'TV - English', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW()),
  ('KTVZ', 'Sascha Rasmussen', 'sascha.rasmussen@ktvz.com', NULL, 'Direct: 541.617.6246 Mobile: 310.948.5600',
   '62990 O.B. Riley Rd', 'Bend', 'OR', '97703',
   'TV - English', 'Market: Central Oregon (CRC)
Kristin Paulson | kristin.paulson@kfxo.com | Direct: 541.617.6206 Cell: 541.610.5693', 'active',
   1, NOW(), NOW()),
  ('Combined Communications', 'Heather Koch', 'heather@combinedcommunications.com', NULL, '541.585.3555',
   NULL, NULL, NULL, NULL,
   'Radio - English', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW()),
  ('Horizon Broadcast Group', 'Billy Jones', 'bjones@horizonbroadcastinggroup.com', NULL, '541.921.7046',
   NULL, NULL, NULL, NULL,
   'Radio - English', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW()),
  ('Backyard Bend', 'Sarah Lauderdale', 'sarah@backyardmedia.net', NULL, '949.689.2269',
   NULL, NULL, NULL, NULL,
   'Radio - English', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW()),
  ('Madras Pioneer', 'Angie Bernard', 'angela.bernard@youroregonnews.com', NULL, '541-903-4080',
   '35 SE C Street Suite C', 'Madras', 'OR', '97741',
   'Newspaper', 'Market: Central Oregon (CRC)
News | news@madraspioneer.com
Jason Chaney | jason.chaney@madraspioneer.com | 971.204.7778', 'active',
   1, NOW(), NOW()),
  ('Central Oregonian', NULL, NULL, NULL, NULL,
   '350 N.E. Belknap St', 'Prineville', 'OR', '97754',
   'Newspaper', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW()),
  ('The Bulletin', 'Jacki Kouba', 'jkouba@bendbulletin.com', NULL, '206.919.0982',
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Central Oregon (CRC)
News Room | news@bendbulletin.com
Regional Event Calendar | calendar@bendbulletin.com', 'active',
   1, NOW(), NOW()),
  ('Columbia Gorge News', 'Kim Horton', 'kimh@gorgenews.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW()),
  ('.', 'Rachel Harrison', 'rachelh@gorgenews.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Central Oregon (CRC)
Richard Joyce | richardj@gorgenews.com
News | news@gorgenews.com', 'active',
   1, NOW(), NOW()),
  ('Redmond Spokesman', 'newsroom', 'news@redmondspokesman.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW()),
  ('The Register Guard', 'Chris Hansen', 'chansen@registerguard.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Central Oregon (CRC)
Press Release | ssregisterguardlegals@gannett.com
Jarrid Denney | jdenney@gannett.com
Samantha Pierotti | spierotti@gannett.com', 'active',
   1, NOW(), NOW()),
  ('Eugene Weekly', 'Camilla Mortensen', 'editor@eugeneweekly.com', NULL, NULL,
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Central Oregon (CRC)
Dave Newman | dave@eugeneweekly.com', 'active',
   1, NOW(), NOW()),
  ('The Nugget', 'Kimberly Young', 'ads@nuggetnews.com', NULL, 'Office: 541.549.9941  Cell: 541.699.8361',
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW()),
  ('The Source Weekly', 'Ashley Sarvis', 'ashley@bendsource.com', NULL, '541.383.0800',
   NULL, NULL, NULL, NULL,
   'Newspaper', 'Market: Central Oregon (CRC)', 'active',
   1, NOW(), NOW());
