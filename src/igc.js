var fl_startdate = null, fl_extensions = null, fl_tzoffset = 0;
const FL_IRE = /I(\d\d)(\d{4}.{3})+/;
const FL_EXTRE = /(\d{2})(\d{2})(.{3})/;
function parseIRecord(line) {
  const extensions = {};
  line = line??'';
  if (FL_IRE.test(line)) {
    let matches = line.match(FL_IRE);
    let nbr = parseInt(matches[1]);
    line = line.substring(3);
    for (let i = 0; i < nbr; i++) {
      matches = line.match(FL_EXTRE);
      if (matches == null || matches.length != 4) continue;
      extensions[matches[3]] = {'start': parseInt(matches[1])-1, 'end': parseInt(matches[2])};
      line = line.substring(i+7);
    }
  }
  return extensions;
}
function parseBRecord(r) {
  //0000000000111111111122222222223333333
  //0123456789012345678901234567890123456
  //B0925064454728N00535480EA016100161528
  let date = new Date(fl_startdate);
  date.setHours(parseInt(r.substr(1,2)));
  date.setMinutes(parseInt(r.substr(3,2)));
  date.setSeconds(parseInt(r.substr(5,2))+fl_tzoffset);
  let altbaro = parseInt(r.substr(25,5));
  let altgps = parseInt(r.substr(30,5));
  //if (alt == 0) alt = altgps;
  let lat = parseFloat(r.substr(7,2));
  let mmmext = 0;
  if (fl_extensions.hasOwnProperty('LAD')) {
    mmmext = parseInt(r.substring(fl_extensions['LAD'].start, fl_extensions['LAD'].end));
  }
  lat += parseFloat((r.substr(9,2)+'.'+r.substr(11,3)+mmmext))/60;
  lat *=  r[14]=='N'?1:-1;
  let lon = parseFloat(r.substr(15,3));
  mmmext = 0;
  if (fl_extensions.hasOwnProperty('LOD')) {
    mmmext = parseInt(r.substring(fl_extensions['LOD'].start, fl_extensions['LOD'].end));
  }
  lon += parseFloat((r.substr(18,2)+'.'+r.substr(20,3)+mmmext))/60;
  lon *=  r[23]=='E'?1:-1;
  return {
    lat: lat,
    lon: lon,
    time: date,
    /*alt: {
      baro: parseInt(r.substr(25,5)),
      gps: parseInt(r.substr(30,5))
    },*/
    alt: window.dispmodealt!='baro'?altgps:altbaro,
    altbaro: altbaro,
    altgps: altgps
  };
}
async function parseIGC(igccont, launchtime=null, paraglidername=null, cletimezonedb=null, tzoffset=null) {
  if (typeof igccont !== 'string' || !igccont.length) return null;
  let points = [];
  fl_tzoffset = tzoffset;
  let lines = igccont.split(/\r?\n/);
  //0000000000111111111
  //0123456789012345678
  //HFDTEDATE:100522,01
  fl_startdate = new Date();
  // todo : remplacer ces deux lignes par un reduce
  let startflightsection = -1, endflightsection = -1;
  let startlinesindex = lines.filter(l => l=l.trim().startsWith('HFDTE')).map(l => lines.indexOf(l));
  startlinesindex.forEach(i => {
    let startdateline = lines[i];
    let tmpstartdate = new Date();
    if (startdateline) {
      let sidx = startdateline.startsWith('HFDTEDATE')?10:5;
      tmpstartdate.setDate(parseInt(startdateline.substring(sidx,sidx+2)));
      tmpstartdate.setMonth(parseInt(startdateline.substring(sidx+2,sidx+4))-1);
      let year = 2000+parseInt(startdateline.substring(sidx+4,sidx+6));
      if (year > tmpstartdate.getFullYear()) year -= 100;
      tmpstartdate.setFullYear(year);
    }
    if (tmpstartdate < fl_startdate) {
      fl_startdate = tmpstartdate;
      startflightsection = i;
    }
  });
  if (startlinesindex.length>1 && !confirm('concaténer les vols?')) {
    endflightsection = startlinesindex.find(i => i>startflightsection) ?? lines.length;
    lines=lines.filter((l,i) => i>=startflightsection&&i<endflightsection);
    //let otherlines=lines.filter((l,i) => i<startflightsection||i>=endflightsection);
  }
  fl_extensions = parseIRecord(lines.find(l => l.trim().startsWith('I')));
  let records = lines.filter(l => l.trim().startsWith('B'));
  let pilotname = lines.find(l => l.trim().startsWith('HFPLTPILOTINCHARGE'))?.split(':')[1];
  if (!paraglidername) {
    paraglidername = lines.find(l => l.trim().startsWith('HFGTYGLIDERTYPE'))?.split(':')[1];
  }
  if (typeof fl_tzoffset !== 'number' || fl_tzoffset < 0) {
    fl_tzoffset = 0;
    if (records.length > 0) {
      // si le décalage horaire n'a pas été fourni on essaie de le deviner à partir de la date de décollage
      if (typeof launchtime?.getMonth === 'function') {
        let fpt = parseBRecord(records[0]);
        fl_tzoffset = (launchtime.getTime()-fpt.time.getTime())/1000;
      } else if (typeof cletimezonedb === 'string' && cletimezonedb.length) { // sinon on essaie de le trouver via l'api timezonedb
        let p = parseBRecord(records[0]);
        let url = `https://api.timezonedb.com/v2.1/get-time-zone?key=${cletimezonedb}&format=json&by=position&lat=${p.lat}&lng=${p.lon}&time=${Math.trunc(p.time.getTime()/1000)}`;
        let data = await fetch(url).then(response => response.json()).catch(err => {});
        //.then(response => response.text())
        //.then(str => new window.DOMParser().parseFromString(str, "text/xml"))
        fl_tzoffset = data && typeof data.gmtOffset === 'number' ? data.gmtOffset : 0;
        //fl_tzoffset = parseInt(data.getElementsByTagName("gmtOffset")[0].textContent);
      }
    }
  }
  records.forEach(r => {
    points.push(parseBRecord(r));
  });
  if (!points.some(p => p.altbaro != 0)) {
    points = points.map(pt => {pt.alt=pt.altbaro=pt.altgps;return pt});
  }
  points.sort((a, b) => a.time-b.time);
  let interval = Math.ceil((points[points.length - 1].time.getTime() - points[0].time.getTime())/points.length);
  interval = Math.round(interval/1000)*1000;
  let gap = 0;
  let j = 0, k = 0;
  let clone = null;
  for (let i=1; i<points.length; i++) {
    gap = points[i].time.getTime() - points[i-1].time.getTime();
    if (gap > interval) {
      //let url = location.protocol+'//'+location.hostname+(location.port?":"+location.port:"")+location.pathname+(location.search?location.search:"");
      //\n${url}#${points[i].time.getTime()}
      j = i+gap/interval;
      k = i-1;
      // TODO : tracer les points manquants entre pos/alt[i] et pos/alt[i+1]
      console.log(`trou dans la trace de ${gap}ms @${points[i].time.toLocaleString()}, comblé par ${j-i} point(s) virtuel(s)`);
      for (; i<j; i++) {
        clone = JSON.parse(JSON.stringify(points[i-1]));
        clone.time = new Date(new Date(clone.time).getTime() + interval);
        points.splice(i, 0, clone);
      }
    }
  }
  return  {
    points : points,
    startdate : fl_startdate,
    pilotname : pilotname,
    paraglidername : paraglidername,
    tzoffset : fl_tzoffset
  };
}

async function loadIGC(id) {
  let filename = `Tracklogs/${id}.igc`;
  try {
    let r = await fetch(filename);
    const contentType = r.headers.get("content-type");
    if (contentType && contentType.indexOf("application/json") < 0) throw new Error('Bad IGC file/Not found');
    return await r.text();
  } catch (e) {
    console.error(e);
  }
  return null;
}
