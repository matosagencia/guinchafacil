-- Catálogo Brasil v5
-- Amplia fabricante -> modelo -> categoria/ano para o cadastro do cliente.
-- A faixa de ano é referência de disponibilidade no Brasil; o ano real
-- continua sendo confirmado pelo cliente no formulário.

INSERT INTO vehicle_models (brand_id, name)
SELECT b.id, x.modelo
FROM (
  SELECT 'Volkswagen' marca,'Polo' modelo UNION ALL SELECT 'Volkswagen','Polo Track' UNION ALL SELECT 'Volkswagen','Virtus' UNION ALL SELECT 'Volkswagen','Nivus' UNION ALL SELECT 'Volkswagen','T-Cross' UNION ALL SELECT 'Volkswagen','Taos' UNION ALL SELECT 'Volkswagen','Tera' UNION ALL SELECT 'Volkswagen','Tiguan' UNION ALL SELECT 'Volkswagen','Jetta' UNION ALL SELECT 'Volkswagen','Saveiro' UNION ALL SELECT 'Volkswagen','Amarok' UNION ALL SELECT 'Volkswagen','ID.4' UNION ALL SELECT 'Volkswagen','ID. Buzz'
  UNION ALL SELECT 'Chevrolet','Onix Plus' UNION ALL SELECT 'Chevrolet','Spin' UNION ALL SELECT 'Chevrolet','Tracker' UNION ALL SELECT 'Chevrolet','Montana' UNION ALL SELECT 'Chevrolet','S10' UNION ALL SELECT 'Chevrolet','Silverado' UNION ALL SELECT 'Chevrolet','Trailblazer' UNION ALL SELECT 'Chevrolet','Equinox' UNION ALL SELECT 'Chevrolet','Blazer EV RS' UNION ALL SELECT 'Chevrolet','Spark EUV' UNION ALL SELECT 'Chevrolet','Captiva EV'
  UNION ALL SELECT 'Fiat','Mobi' UNION ALL SELECT 'Fiat','Argo' UNION ALL SELECT 'Fiat','Cronos' UNION ALL SELECT 'Fiat','Pulse' UNION ALL SELECT 'Fiat','Fastback' UNION ALL SELECT 'Fiat','Toro' UNION ALL SELECT 'Fiat','Strada' UNION ALL SELECT 'Fiat','Titano' UNION ALL SELECT 'Fiat','500e'
  UNION ALL SELECT 'Ford','Ranger' UNION ALL SELECT 'Ford','Ranger Raptor' UNION ALL SELECT 'Ford','Maverick' UNION ALL SELECT 'Ford','F-150' UNION ALL SELECT 'Ford','Territory' UNION ALL SELECT 'Ford','Bronco Sport' UNION ALL SELECT 'Ford','Mustang' UNION ALL SELECT 'Ford','Transit'
  UNION ALL SELECT 'Hyundai','HB20S' UNION ALL SELECT 'Hyundai','Creta' UNION ALL SELECT 'Hyundai','Tucson' UNION ALL SELECT 'Hyundai','Santa Fe' UNION ALL SELECT 'Hyundai','i30' UNION ALL SELECT 'Hyundai','HR'
  UNION ALL SELECT 'Renault','Kwid' UNION ALL SELECT 'Renault','Stepway' UNION ALL SELECT 'Renault','Sandero' UNION ALL SELECT 'Renault','Logan' UNION ALL SELECT 'Renault','Duster' UNION ALL SELECT 'Renault','Oroch' UNION ALL SELECT 'Renault','Master' UNION ALL SELECT 'Renault','Kangoo'
  UNION ALL SELECT 'Honda','City' UNION ALL SELECT 'Honda','City Hatchback' UNION ALL SELECT 'Honda','HR-V' UNION ALL SELECT 'Honda','ZR-V' UNION ALL SELECT 'Honda','CR-V' UNION ALL SELECT 'Honda','Civic' UNION ALL SELECT 'Honda','Civic Type R' UNION ALL SELECT 'Honda','Fit' UNION ALL SELECT 'Honda','CG 160' UNION ALL SELECT 'Honda','Biz 125' UNION ALL SELECT 'Honda','PCX' UNION ALL SELECT 'Honda','X-ADV'
  UNION ALL SELECT 'Toyota','Yaris' UNION ALL SELECT 'Toyota','Yaris Sedan' UNION ALL SELECT 'Toyota','Corolla Cross' UNION ALL SELECT 'Toyota','Hilux' UNION ALL SELECT 'Toyota','SW4' UNION ALL SELECT 'Toyota','RAV4' UNION ALL SELECT 'Toyota','Prius' UNION ALL SELECT 'Toyota','Yaris Cross'
  UNION ALL SELECT 'Yamaha','Fazer FZ25' UNION ALL SELECT 'Yamaha','Fazer FZ15' UNION ALL SELECT 'Yamaha','Crosser 150' UNION ALL SELECT 'Yamaha','Lander 250' UNION ALL SELECT 'Yamaha','NMAX 160' UNION ALL SELECT 'Yamaha','Fluо 125' UNION ALL SELECT 'Yamaha','MT-03' UNION ALL SELECT 'Yamaha','MT-07' UNION ALL SELECT 'Yamaha','R3' UNION ALL SELECT 'Yamaha','XTZ 250'
  UNION ALL SELECT 'Mercedes-Benz','Classe A' UNION ALL SELECT 'Mercedes-Benz','Classe C' UNION ALL SELECT 'Mercedes-Benz','Classe E' UNION ALL SELECT 'Mercedes-Benz','GLA' UNION ALL SELECT 'Mercedes-Benz','GLB' UNION ALL SELECT 'Mercedes-Benz','GLC' UNION ALL SELECT 'Mercedes-Benz','Sprinter' UNION ALL SELECT 'Mercedes-Benz','Accelo 815' UNION ALL SELECT 'Mercedes-Benz','Atego 1719'
  UNION ALL SELECT 'Volvo','XC40' UNION ALL SELECT 'Volvo','EX30' UNION ALL SELECT 'Volvo','XC60' UNION ALL SELECT 'Volvo','XC90' UNION ALL SELECT 'Volvo','C40' UNION ALL SELECT 'Volvo','FH' UNION ALL SELECT 'Volvo','FM' UNION ALL SELECT 'Volvo','VM 270'
  UNION ALL SELECT 'Iveco','Daily' UNION ALL SELECT 'Iveco','Tector' UNION ALL SELECT 'Iveco','Hi-Way' UNION ALL SELECT 'Iveco','S-Way' UNION ALL SELECT 'Iveco','CityClass'
  UNION ALL SELECT 'Scania','P 250' UNION ALL SELECT 'Scania','P 320' UNION ALL SELECT 'Scania','G 410' UNION ALL SELECT 'Scania','R 450' UNION ALL SELECT 'Scania','K  chassis'
  UNION ALL SELECT 'MAN','TGX 29.480' UNION ALL SELECT 'MAN','TGX 28.440' UNION ALL SELECT 'MAN','TGS 28.440' UNION ALL SELECT 'MAN','TGM 17.250'
  UNION ALL SELECT 'Nissan','Versa' UNION ALL SELECT 'Nissan','Sentra' UNION ALL SELECT 'Nissan','Kicks' UNION ALL SELECT 'Nissan','Frontier' UNION ALL SELECT 'Nissan','Leaf' UNION ALL SELECT 'Nissan','X-Trail'
  UNION ALL SELECT 'Jeep','Renegade' UNION ALL SELECT 'Jeep','Compass' UNION ALL SELECT 'Jeep','Commander' UNION ALL SELECT 'Jeep','Wrangler' UNION ALL SELECT 'Jeep','Gladiator' UNION ALL SELECT 'Jeep','Avenger'
  UNION ALL SELECT 'Kia','Picanto' UNION ALL SELECT 'Kia','Cerato' UNION ALL SELECT 'Kia','Sportage' UNION ALL SELECT 'Kia','Sorento' UNION ALL SELECT 'Kia','Carnival' UNION ALL SELECT 'Kia','Niro' UNION ALL SELECT 'Kia','EV5'
  UNION ALL SELECT 'Mitsubishi','Eclipse Cross' UNION ALL SELECT 'Mitsubishi','Outlander' UNION ALL SELECT 'Mitsubishi','Outlander PHEV' UNION ALL SELECT 'Mitsubishi','Pajero Sport' UNION ALL SELECT 'Mitsubishi','Triton' UNION ALL SELECT 'Mitsubishi','L200'
  UNION ALL SELECT 'Suzuki','Jimny' UNION ALL SELECT 'Suzuki','Jimny Sierra' UNION ALL SELECT 'Suzuki','Vitara' UNION ALL SELECT 'Suzuki','S-Cross' UNION ALL SELECT 'Suzuki','Swift'
  UNION ALL SELECT 'BYD','Dolphin' UNION ALL SELECT 'BYD','Dolphin Mini' UNION ALL SELECT 'BYD','Seal' UNION ALL SELECT 'BYD','Song Plus' UNION ALL SELECT 'BYD','Yuan Pro' UNION ALL SELECT 'BYD','Yuan Plus' UNION ALL SELECT 'BYD','King' UNION ALL SELECT 'BYD','Tan' UNION ALL SELECT 'BYD','Shark'
) x JOIN vehicle_brands b ON b.name = x.marca
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Versões operacionais genéricas tornam a seleção por ano compatível com o
-- catálogo; o ano específico informado pelo cliente continua prevalecendo.
INSERT INTO vehicle_versions (model_id, name, start_year, end_year, fuel_type, body_type, operational_category_id, active)
SELECT vm.id,
       'Linha Brasil',
       x.ano_inicio, 2026, x.combustivel, x.carroceria, c.id, 1
FROM (
  SELECT 'Volkswagen' marca,'Polo' modelo,2018 ano_inicio,'flex' combustivel,'hatch' carroceria,'PASSENGER_HATCH' cat UNION ALL SELECT 'Volkswagen','T-Cross',2019,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Volkswagen','Taos',2021,'flex','suv','SUV_HEAVY' UNION ALL SELECT 'Volkswagen','Amarok',2010,'diesel','pickup','PICKUP_HEAVY'
  UNION ALL SELECT 'Chevrolet','Onix',2013,'flex','hatch','PASSENGER_HATCH' UNION ALL SELECT 'Chevrolet','Tracker',2020,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Chevrolet','S10',2012,'diesel','pickup','PICKUP_HEAVY' UNION ALL SELECT 'Chevrolet','Silverado',2023,'diesel','pickup','PICKUP_HEAVY'
  UNION ALL SELECT 'Fiat','Argo',2017,'flex','hatch','PASSENGER_HATCH' UNION ALL SELECT 'Fiat','Pulse',2021,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Fiat','Toro',2016,'flex','pickup','PICKUP_LIGHT' UNION ALL SELECT 'Fiat','Strada',2009,'flex','pickup','PICKUP_LIGHT' UNION ALL SELECT 'Fiat','Titano',2024,'diesel','pickup','PICKUP_HEAVY'
  UNION ALL SELECT 'Ford','Ranger',2013,'diesel','pickup','PICKUP_HEAVY' UNION ALL SELECT 'Ford','Maverick',2022,'hibrido','pickup','PICKUP_LIGHT' UNION ALL SELECT 'Ford','Territory',2020,'gasolina','suv','SUV_HEAVY' UNION ALL SELECT 'Ford','Transit',2018,'diesel','van','VAN_LIGHT'
  UNION ALL SELECT 'Hyundai','HB20',2012,'flex','hatch','PASSENGER_HATCH' UNION ALL SELECT 'Hyundai','Creta',2017,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Hyundai','HR',2005,'diesel','van','COMMERCIAL_LIGHT'
  UNION ALL SELECT 'Renault','Kwid',2017,'flex','hatch','PASSENGER_COMPACT' UNION ALL SELECT 'Renault','Duster',2011,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Renault','Oroch',2015,'flex','pickup','PICKUP_LIGHT' UNION ALL SELECT 'Renault','Master',2013,'diesel','van','VAN_LIGHT'
  UNION ALL SELECT 'Honda','City',2009,'flex','sedan','PASSENGER_SEDAN' UNION ALL SELECT 'Honda','HR-V',2015,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Honda','CG 160',2015,'flex','moto','MOTORCYCLE_LIGHT' UNION ALL SELECT 'Honda','PCX',2013,'gasolina','moto','MOTORCYCLE_LIGHT'
  UNION ALL SELECT 'Toyota','Corolla',1993,'flex','sedan','PASSENGER_SEDAN' UNION ALL SELECT 'Toyota','Corolla Cross',2021,'hibrido','suv','SUV_LIGHT' UNION ALL SELECT 'Toyota','Hilux',2005,'diesel','pickup','PICKUP_HEAVY' UNION ALL SELECT 'Toyota','SW4',2005,'diesel','suv','SUV_HEAVY'
  UNION ALL SELECT 'Yamaha','Factor 150',2016,'flex','moto','MOTORCYCLE_LIGHT' UNION ALL SELECT 'Yamaha','NMAX 160',2017,'gasolina','moto','MOTORCYCLE_LIGHT' UNION ALL SELECT 'Yamaha','MT-03',2017,'gasolina','moto','MOTORCYCLE_HEAVY'
  UNION ALL SELECT 'Nissan','Versa',2011,'flex','sedan','PASSENGER_SEDAN' UNION ALL SELECT 'Nissan','Kicks',2016,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Nissan','Frontier',1998,'diesel','pickup','PICKUP_HEAVY'
  UNION ALL SELECT 'Jeep','Renegade',2015,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Jeep','Compass',2016,'flex','suv','SUV_HEAVY' UNION ALL SELECT 'Jeep','Commander',2021,'flex','suv','SUV_HEAVY'
  UNION ALL SELECT 'Kia','Sportage',2011,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Kia','Sorento',2011,'gasolina','suv','SUV_HEAVY' UNION ALL SELECT 'Kia','Niro',2023,'hibrido','suv','HYBRID_LIGHT'
  UNION ALL SELECT 'Mitsubishi','Eclipse Cross',2019,'gasolina','suv','SUV_LIGHT' UNION ALL SELECT 'Mitsubishi','Triton',2024,'diesel','pickup','PICKUP_HEAVY' UNION ALL SELECT 'Mitsubishi','L200',2017,'diesel','pickup','PICKUP_HEAVY'
  UNION ALL SELECT 'Suzuki','Jimny',2013,'gasolina','suv','SUV_LIGHT' UNION ALL SELECT 'Suzuki','Jimny Sierra',2019,'gasolina','suv','SUV_LIGHT'
  UNION ALL SELECT 'BYD','Dolphin',2023,'eletrico','hatch','ELECTRIC_LIGHT' UNION ALL SELECT 'BYD','Dolphin Mini',2024,'eletrico','hatch','ELECTRIC_LIGHT' UNION ALL SELECT 'BYD','Song Plus',2023,'hibrido','suv','HYBRID_LIGHT' UNION ALL SELECT 'BYD','Shark',2024,'hibrido','pickup','PICKUP_HEAVY'
) x
JOIN vehicle_brands b ON b.name=x.marca
JOIN vehicle_models vm ON vm.brand_id=b.id AND vm.name=x.modelo
JOIN vehicle_operational_categories c ON c.code=x.cat
WHERE NOT EXISTS (SELECT 1 FROM vehicle_versions v WHERE v.model_id=vm.id AND v.name='Linha Brasil');
