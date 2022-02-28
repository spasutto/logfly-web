
class GraphGPX {

  constructor(elem, elevationservice, showvz, showvx) {
    this.resetInfos();
    this.elem = elem;
    if (typeof elevationservice == 'string' && elevationservice.trim().length > 0) {
      this.elevationservice = elevationservice;
    }
    this.elevcalls = 0;
    this.showvz = showvz === true;
    this.showvx = showvx === true;
    this.delaytouch = 0;
    this.createCanvas();
  }

  set showVz(showvz) {
    this.showvz = showvz === true;
    this.paint();
  }
  get showVz() {
    return this.showvz === true;
  }

  set showVx(showvx) {
    this.showvx = showvx === true;
    this.paint();
  }
  get showVx() {
    return this.showvx === true;
  }

  resetInfos() {
    this.fi = { 'pts': [], maxalt: -1000, minalt: 100000, maxvz:-1000, minvz:100000, maxvx:-1000, minvx:100000, start: new Date };
  }

  createCanvas() {
    this.canvas = document.createElement("canvas");
    let elem = document.createTextNode('Navigateur obsolète!');
    this.canvas.appendChild(elem);

    this.canvas2 = document.createElement("canvas");
    elem = document.createTextNode('Navigateur obsolète!');
    this.canvas2.appendChild(elem);

    this.elem.appendChild(this.canvas2);
    this.elem.appendChild(this.canvas);

    this.canvas2.style.width =this.canvas.style.width ='100%';
    this.canvas2.style.height=this.canvas.style.height='100%';
    this.canvas2.style.position = 'absolute';
    new ResizeObserver(this.fitCanvas.bind(this)).observe(this.elem);
    this.ctx = this.canvas.getContext('2d');
    this.ctx2 = this.canvas2.getContext('2d');
    this.canvas2.addEventListener('mousemove', this.mousemove.bind(this));
    this.canvas2.addEventListener('click', this.click.bind(this));
    this.canvas2.addEventListener('wheel', this.wheel.bind(this));
    if ('ontouchstart' in document.documentElement) {
      this.canvas2.addEventListener("touchstart", this.touchevts, true);
      this.canvas2.addEventListener("touchmove", this.touchevts, true);
      this.canvas2.addEventListener("touchend", this.touchevts, true);
    }

    elem = document.createElement("button");
    elem.style.position = 'absolute';
    elem.style.bottom = '5px';
    elem.style.right = '5px';
    elem.style.fontWeight = 'bolder';
    elem.appendChild(document.createTextNode('\u2699'));
    elem.onclick = function () {
      let pp = document.getElementById('grphcfig');
      pp.style.display = pp.style.display == 'block' ? 'none' : 'block';
    };
    this.elem.appendChild(elem);

    elem = document.createElement("div");
    elem.id = 'grphcfig';
    elem.style.position = 'absolute';
    elem.style.bottom = '24px';
    elem.style.right = '5px';
    elem.style.padding = '1px';
    elem.style.display = 'none';
    elem.style.backgroundColor = '#cfcfcf';
    elem.style.border = 'solid 1px grey';

    let elem2 = document.createElement("input");
    elem2.setAttribute('type', 'checkbox');
    elem2.id = 'grphshowvz';
    if (this.showvz) {
      elem2.checked = true;
    }
    elem2.onclick = function (evt) {
      this.showVz = evt.currentTarget.checked;
    }.bind(this);
    elem.appendChild(elem2);
    elem2 = document.createElement("label");
    elem2.setAttribute('for', 'grphshowvz');
    elem2.appendChild(document.createTextNode('afficher vz'));
    elem.appendChild(elem2);
    this.elem.appendChild(elem);

    elem2 = document.createElement("input");
    elem2.setAttribute('type', 'checkbox');
    elem2.id = 'grphshowvx';
    if (this.showvx) {
      elem2.checked = true;
    }
    elem2.onclick = function (evt) {
      this.showVx = evt.currentTarget.checked;
    }.bind(this);
    elem.appendChild(elem2);
    elem2 = document.createElement("label");
    elem2.setAttribute('for', 'grphshowvx');
    elem2.appendChild(document.createTextNode('afficher vx'));
    elem.appendChild(elem2);
    this.elem.appendChild(elem);

    this.paint();
  }

  addEventListener(evtname, fct) {
    this.elem.addEventListener(evtname, fct, false);
  }

  fitCanvas() {
    this.canvas2.width  = this.canvas.width  = this.canvas.offsetWidth;
    this.canvas2.height = this.canvas.height = this.canvas.offsetHeight;
    this.paint();
  }

  touchevts(e) {
    let theTouch = e.changedTouches[0];
    let mouseEv;

    switch(e.type)
    {
      case "touchstart": mouseEv = "mousedown"; this.delaytouch = Date.now(); break;
      case "touchend": mouseEv = "mouseup"; this.delaytouch = Date.now() - this.delaytouch; break;
      case "touchmove":  mouseEv="mousemove"; break;
      default: return;
    }

    if (e.type == "touchend" && this.delaytouch < 250) {
      mouseEv = "click";
    }

    let mouseEvent = document.createEvent("MouseEvent");
    mouseEvent.initMouseEvent(mouseEv, true, true, window, 1, theTouch.screenX, theTouch.screenY, theTouch.clientX, theTouch.clientY, false, false, false, false, 0, null);
    theTouch.target.dispatchEvent(mouseEvent);

    e.preventDefault();
  }

  wheel(e) {
    let event = new CustomEvent('onwheel', {"detail": e.deltaY});
    this.elem.dispatchEvent(event);
  }

  click(e) {
    if (!Array.isArray(this.fi.pts) || this.fi.pts.length <= 0)
      return;
    let rect = this.elem.getBoundingClientRect(),
        x = e.clientX - rect.left,
        idxpt = Math.round(x/this.incx)*this.incr;
    if (idxpt > this.fi.pts.length-1) {
      idxpt = this.fi.pts.length-1;
      x = Math.round((idxpt / this.incr) * this.incx);
    }
    let curpt = this.fi.pts[idxpt];
    let event = new CustomEvent('onclick', {"detail": curpt});
    this.elem.dispatchEvent(event);
  }

  mousemove(e) {
    if (!Array.isArray(this.fi.pts) || this.fi.pts.length <= 0)
      return;
    let rect = this.elem.getBoundingClientRect(),
        x = e.clientX - rect.left,
        idxpt = Math.round(x/this.incx)*this.incr;
    if (idxpt > this.fi.pts.length-1) {
      idxpt = this.fi.pts.length-1;
      x = Math.round((idxpt / this.incr) * this.incx);
    }
    this.ctx2.clearRect(0, 0, this.canvas2.width, this.canvas2.height);
    this.ctx2.lineWidth = 1;
    this.ctx2.beginPath();
    this.ctx2.moveTo(x,0);
    this.ctx2.lineTo(x,this.canvas2.height);
    this.ctx2.stroke();
    this.ctx2.fillStyle = "#FFFF9C8F";
    this.ctx2.fillRect(this.canvas2.width-70, 0, this.canvas2.width, 50);
    let curpt = this.fi.pts[idxpt];
    this.ctx2.font = '10px sans-serif';
    this.ctx2.fillStyle = "#5f5f5f";
    let posx=this.canvas2.width - 70, posy = 0;
    this.ctx2.fillText(curpt.alt + ' m AMSL', posx, posy+=10);
    if (typeof curpt.gndalt == 'number')
      this.ctx2.fillText(Math.round(curpt.alt-curpt.gndalt)+' m AGL', posx, posy+=10);
    this.ctx2.fillText(curpt.vz + ' m/s', posx, posy+=10);
    this.ctx2.fillText(curpt.vx + ' km/h', posx, posy+=10);
    let t = new Date(Date.UTC(1970, 0, 1));
    t.setUTCSeconds((curpt.time.getTime() - this.start.getTime()) / 1000);
    this.ctx2.fillText(curpt.time.toLocaleString('fr-FR'/*, { timeZone: 'UTC' }*/).substr(-8, 5) + " ("+t.toLocaleString('fr-FR', { timeZone: 'UTC' }).substr(-8, 5)+")", posx, posy+=10);
    let event = new CustomEvent('onposchanged', {"detail": curpt});
    this.elem.dispatchEvent(event);
  }

  paint() {
    this.ctx.fillStyle = "#EAF8C4";
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.strokeStyle = "#5f5f5f";
    this.ctx.lineWidth = 1;
    if (!Array.isArray(this.fi.pts) || this.fi.pts.length <= 0) {
      this.ctx.font = '18px sans-serif';
      this.ctx.fillText('chargement...', (this.canvas.width / 2) - 20, (this.canvas.height / 2) - 5);
      return;
    }
    this.ctx.font = '10px sans-serif';
    let t = 0, x = 0, y = 0;
    let minaltg = Math.floor(this.fi.minalt / 100) * 100;
    let maxaltg = Math.ceil(this.fi.maxalt / 100) * 100;
    if (maxaltg == minaltg) maxaltg += 500;
    //console.log(this.fi.minalt, this.fi.maxalt, minaltg, maxaltg, this.fi.pts[0]);
    let altdiff = maxaltg - minaltg;//this.fi.maxalt-this.fi.minalt;
    altdiff *= 1.05;
    let maxvx = Math.ceil(Math.min(100, this.fi.maxvx));
    let coefh = this.canvas.height / altdiff;
    let coefhvz = this.canvas.height / (this.fi.maxvz-this.fi.minvz);
    let coefhvx = this.canvas.height / (maxvx-this.fi.minvx);
    let getY = function (alt) { return this.canvas.height - Math.round(coefh * (alt - this.fi.minalt)); }.bind(this);
    let getYVz = function (vz) { return this.canvas.height - Math.round(coefhvz * (vz - this.fi.minvz)); }.bind(this);
    let getYVx = function (vx) { return this.canvas.height - Math.round(coefhvx * (vx - this.fi.minvx)); }.bind(this);
    this.incx = this.canvas.width / this.fi.pts.length;
    this.incr = 1;
    if (this.incx < 1) {
      this.incr = 1 / this.incx;
      this.incx = 1;
    }
    this.incx = Math.floor(this.incx);
    this.incr = Math.ceil(this.incr);

    // heures
    let firsthour = new Date(this.start); firsthour.setMilliseconds(0); firsthour.setSeconds(0); firsthour.setMinutes(0);
    firsthour = 3600 - ((this.start - firsthour) / 1000);
    let secstotal = (this.fi.pts[this.fi.pts.length - 1].time - this.start) / 1000;
    let inct = (secstotal / (this.incx * this.fi.pts.length)) / this.incr;
    this.ctx.strokeStyle = "#afafaf";
    this.ctx.beginPath();
    x = 0;
    for (t = firsthour; t < secstotal; t += 3600) {
      x = Math.round(inct * t);
      this.ctx.moveTo(x, 0);
      this.ctx.lineTo(x, this.canvas.height);
    }
    this.ctx.closePath();
    this.ctx.stroke();

    // gnd alt
    if (typeof this.fi.pts[0].gndalt == 'number') {
      this.ctx.fillStyle = "#afafaf";
      this.ctx.strokeStyle = "#5f5f5f";
      x = 0;
      y = getY(this.fi.pts[0].gndalt)
      this.ctx.beginPath();
      this.ctx.moveTo(0, y);
      for (t = 0; t < this.fi.pts.length; t += this.incr) {
        y = getY(this.fi.pts[t].gndalt);
        if (y >= 0 && y < this.canvas.height) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.lineTo(x, this.canvas.height);
      this.ctx.lineTo(0, this.canvas.height);
      this.ctx.closePath();
      this.ctx.stroke();
      this.ctx.fill();
    }

    this.ctx.strokeStyle = "#002fff";

    // alt
    x = 0;
    y = getY(this.fi.pts[0].alt);
    this.ctx.beginPath();
    this.ctx.moveTo(x, y);
    for (t = 0; t < this.fi.pts.length; t += this.incr) {
      y = getY(this.fi.pts[t].alt);
      if (y >= 0 && y < this.canvas.height) {
        this.ctx.lineTo(x, y);
      }
      x += this.incx;
    }
    this.ctx.stroke();

    // vz
    if (this.showvz) {
      this.ctx.strokeStyle = "#af00af";
      x = 0;
      y = getYVz(this.fi.pts[0].vz);
      this.ctx.beginPath();
      this.ctx.moveTo(x, y);
      for (t = 0; t < this.fi.pts.length; t += this.incr) {
        y = getYVz(this.fi.pts[t].vz);
        if (y >= 0 && y < this.canvas.height) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.stroke();
    }

    // vx
    if (this.showvx) {
      this.ctx.strokeStyle = "#00afaf";
      x = 0;
      y = getYVx(this.fi.pts[0].vx);
      this.ctx.beginPath();
      this.ctx.moveTo(x, y);
      for (t = 0; t < this.fi.pts.length; t += this.incr) {
        y = getYVx(this.fi.pts[t].vx);
        if (y >= 0 && y < this.canvas.height) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.stroke();
    }

    this.ctx.strokeStyle = "#5f5f5f";
    this.ctx.lineWidth = 2;
    // legende alt
    this.ctx.beginPath();
    this.ctx.moveTo(0, 0);
    this.ctx.lineTo(0, this.canvas.height);
    this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
    this.ctx.stroke();
    this.ctx.fillStyle = "#0f0f0f";
    minaltg = Math.floor(minaltg / 500) * 500;
    for (t = minaltg; t <= maxaltg; t += 500) {
      y = getY(t);
      if (y <= 0 || y > this.canvas.height) continue;
      this.ctx.beginPath();
      this.ctx.moveTo(0, y - 3);
      this.ctx.lineTo(4, y - 3);
      this.ctx.stroke();
      this.ctx.fillText(t, 5, y);
    }

    let minscale = 33;
    // legende vz
    if (this.showvz) {
      this.ctx.strokeStyle = "#8f8f8f";
      this.ctx.fillStyle = "#af00af";
      this.ctx.lineWidth = 1;
      this.ctx.beginPath();
      this.ctx.moveTo(minscale, 0);
      this.ctx.lineTo(minscale, this.canvas.height);
      this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
      this.ctx.stroke();
      //this.ctx.fillStyle = "#0f0f0f";
      let maxvz = Math.ceil(this.fi.maxvz);
      let minvz = Math.floor(this.fi.minvz);
      for (t = minvz; t <= maxvz; t++) {
        y = getYVz(t);
        if (y <= 0 || y > this.canvas.height) continue;
        this.ctx.beginPath();
        this.ctx.moveTo(minscale, y - 3);
        this.ctx.lineTo(minscale+4, y - 3);
        this.ctx.stroke();
        if (t == 0) {
          this.ctx.beginPath();
          this.ctx.moveTo(minscale, y - 3);
          this.ctx.lineTo(this.canvas.width, y - 3);
          this.ctx.stroke();
        }
        this.ctx.fillText(t, minscale+5, y);
      }
      minscale += 20;
    }

    // legende vx
    if (this.showvx) {
      this.ctx.strokeStyle = "#8f8f8f";
      this.ctx.fillStyle = "#00afaf";
      this.ctx.lineWidth = 1;
      this.ctx.beginPath();
      this.ctx.moveTo(minscale, 0);
      this.ctx.lineTo(minscale, this.canvas.height);
      this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
      this.ctx.stroke();
      //this.ctx.fillStyle = "#0f0f0f";
      let minvx = Math.floor(this.fi.minvx);
      for (t = minvx; t <= maxvx; t+=10) {
        y = getYVx(t);
        if (y <= 0 || y > this.canvas.height) continue;
        this.ctx.beginPath();
        this.ctx.moveTo(minscale, y - 3);
        this.ctx.lineTo(minscale+4, y - 3);
        this.ctx.stroke();
        this.ctx.fillText(t, minscale+5, y);
      }
    }
  }

  setFlightInfo(fi) {
    this.fi = fi;
    this.paint();
  }

  setGPX(gpx) {
    // les vz max/min sont en instantané donc très grandes
    const VZMAX = 30;
    const VZMIN = -30;
    let xpts = gpx.getElementsByTagName("trkpt");
    this.resetInfos();
    let i = 0, j = 0, k = 0, alt = 0, lat = 0, lon = 0, latvx = 0, lonvx = 0, vz = 0, vx = 0, tdiff = 0, vzm = [];
    let time, timevx;
    for (i=0; i<xpts.length; i++) {
      alt = parseFloat(xpts[i].getElementsByTagName("ele")[0].textContent);
      time = new Date(xpts[i].getElementsByTagName("time")[0].textContent);
      lat = parseFloat(xpts[i].getAttribute('lat'));
      lon = parseFloat(xpts[i].getAttribute('lon'));
      this.fi.pts.push(
        {
          'lat': lat,
          'lon': lon,
          'alt': alt,
          'time': time,
          'vz': 0,
          'vx': 0,
        });
      if (i == 0) {
        this.start = timevx = time;
        latvx = lat;
        lonvx = lon;
      } else {
        tdiff = (time.getTime() - this.fi.pts[i - 1].time.getTime()) / 1000;
        vz = (alt - this.fi.pts[i - 1].alt) / tdiff;
        // si VZ > max alors on réévalue alt avec VZ -antérieur- ne fonctionne pas, on prends vz = 0 et l'altitude du point précédent
        if (vz > VZMAX || vz < VZMIN) {
          vz = 0;//Math.max(VZMIN, Math.min(VZMAX, vz));//this.fi.pts[i - 1].vz
          this.fi.pts[i].alt = alt = this.fi.pts[i - 1].alt;//vz * tdiff + this.fi.pts[i - 1].alt;
        }
        if (vzm.length < 25) {
          vzm.push(vz);
        } else {
          if (j > 0 && j % 25 == 0) j = 0;
          vzm[j++] = vz;
        }
        vz = vzm.reduce((a, b) => a + b, 0) / vzm.length;
        this.fi.pts[i].vz = Math.round(vz * 10) / 10;
        tdiff = (time.getTime() - timevx.getTime()) / 1000;
        if (tdiff >= 2) {
          vx = this.distance(lat, lon, latvx, lonvx) / tdiff;
          timevx = time;
          latvx = lat;
          lonvx = lon;
        } else {
          vx = this.fi.pts[i - 1].vx;
        }
        this.fi.pts[i].vx = Math.round(3.6*vx);
      }
      if (alt < this.fi.minalt) this.fi.minalt = alt;
      if (alt > this.fi.maxalt) this.fi.maxalt = alt;
      if (vz < this.fi.minvz) this.fi.minvz = vz;
      if (vz > this.fi.maxvz) this.fi.maxvz = vz;
      if (vx < this.fi.minvx) this.fi.minvx = vx;
      if (vx > this.fi.maxvx) this.fi.maxvx = vx;
    }
    // TODO : faire mieux (évaluer vz?)
    this.fi.minalt = Math.max(0, this.fi.minalt);
    this.fi.maxalt = Math.min(10000, this.fi.maxalt);
    this.fi.minvz = Math.max(-15, this.fi.minvz);
    this.fi.maxvz = Math.min(15, this.fi.maxvz);
    this.fi.minvx = Math.max(0, this.fi.minvx);
    this.fi.maxvx = Math.min(200, this.fi.maxvx);
    if (typeof this.elevationservice === 'string') {
      let curelev = 0;
      let locations = [];
      for (let i = 0; i < this.fi.pts.length; i++) {
        locations.push(this.fi.pts[i].lat);
        locations.push(this.fi.pts[i].lon);
        if (i && i % 4999 == 0) {
          this.getElevation(locations, curelev, 5000);
          curelev = i + 1;
          locations = [];
        }
      }
      if (curelev < this.fi.pts.length)
        this.getElevation(locations, curelev, this.fi.pts.length - curelev);
    }
    this.paint();
    return this.fi;
  }

  getElevation(locations, index, count) {
    var xhttp = new XMLHttpRequest();
    let data = { "locations": locations, "doInfills": false, "interpolate": false };
    xhttp.responseType = 'text';
    xhttp.onreadystatechange = function() {
      if (xhttp.readyState == 4 && xhttp.status == 200) {
        if (!xhttp.responseText) return;
        try {
          let alts = eval(xhttp.responseText);
          let j = 0;
          for (let i = index; i < index+count; i++) {
            this.fi.pts[i].gndalt = alts[j++];
          }
        }
        catch (e) { console.log("error \"" + e + "\" while eval " + xhttp.responseText); }
        this.elevcalls--;
        if (this.elevcalls <= 0)
          this.paint();
      }
    }.bind(this);
    xhttp.open("POST", this.elevationservice, true);
    xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhttp.send(JSON.stringify(data));
    this.elevcalls++;
  }

  /**
   * Calculates the great-circle distance between two points, with
   * the Haversine formula.
   * @param float latitudeFrom Latitude of start point in [deg decimal]
   * @param float longitudeFrom Longitude of start point in [deg decimal]
   * @param float latitudeTo Latitude of target point in [deg decimal]
   * @param float longitudeTo Longitude of target point in [deg decimal]
   * @param float earthRadius Mean earth radius in [m]
   * @return float Distance between points in [m] (same as earthRadius)
   */
   distance(latitudeFrom, longitudeFrom, latitudeTo, longitudeTo, earthRadius = 6371000) {
    const deg2rad = deg => (deg * Math.PI) / 180.0;
    // convert from degrees to radians
    let latFrom = deg2rad(latitudeFrom);
    let lonFrom = deg2rad(longitudeFrom);
    let latTo = deg2rad(latitudeTo);
    let lonTo = deg2rad(longitudeTo);

    let latDelta = latTo - latFrom;
    let lonDelta = lonTo - lonFrom;

    let angle = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin(latDelta / 2), 2) +
    Math.cos(latFrom) * Math.cos(latTo) * Math.pow(Math.sin(lonDelta / 2), 2)));
    return angle * earthRadius;
  }
}
