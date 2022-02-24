class GraphGPX {

  constructor(elem, elevationservice) {
    this.resetInfos();
    this.elem = elem;
    if (typeof elevationservice == 'string' && elevationservice.trim().length > 0) {
      this.elevationservice = elevationservice;
    }
    this.elevcalls = 0;
    this.createCanvas();
  }

  resetInfos() {
    this.fi = { 'pts': [], maxalt: -1000, minalt: 100000, maxvz:-1000, minvz:100000, start: new Date };
  }

  createCanvas() {
    this.canvas = document.createElement("canvas");
    var newContent = document.createTextNode('Navigateur obsolète!');
    this.canvas.appendChild(newContent);

    this.canvas2 = document.createElement("canvas");
    newContent = document.createTextNode('Navigateur obsolète!');
    this.canvas2.appendChild(newContent);

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
      case "touchstart": mouseEv="mousedown"; break;
      case "touchend":   mouseEv="mouseup"; break;
      case "touchmove":  mouseEv="mousemove"; break;
      default: return;
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
    let coefh = this.canvas.height / altdiff;
    let getY = function (alt) { return this.canvas.height - Math.round(coefh * (alt - this.fi.minalt)); }.bind(this);
    this.incx = this.canvas.width / this.fi.pts.length;
    this.incr = 1;
    if (this.incx < 1) {
      this.incr = 1 / this.incx;
      this.incx = 1;
    }
    this.incx = Math.floor(this.incx);
    this.incr = Math.ceil(this.incr);

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

    this.ctx.strokeStyle = "#5f5f5f";
    this.ctx.lineWidth = 2;
    this.ctx.beginPath();
    this.ctx.moveTo(0, 0);
    this.ctx.lineTo(0, this.canvas.height);
    this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
    this.ctx.stroke();
    this.ctx.fillStyle = "#0f0f0f";
    y = getY(this.fi.pts[0].alt);
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
  }

  setFlightInfo(fi) {
    this.fi = fi;
    this.paint();
  }

  setGPX(gpx) {
    let xpts = gpx.getElementsByTagName("trkpt");
    this.resetInfos();
    let i = 0, j = 0, k = 0, alt = 0, lat = 0, lon = 0, vz = 0, vzm = [];
    let time;
    for (i=0; i<xpts.length; i++) {
      alt = parseFloat(xpts[i].getElementsByTagName("ele")[0].textContent);
      time = new Date(xpts[i].getElementsByTagName("time")[0].textContent);
      this.fi.pts.push(
        {
          'lat': parseFloat(xpts[i].getAttribute('lat')),
          'lon': parseFloat(xpts[i].getAttribute('lon')),
          'alt': alt,
          'time': time,
          'vz': 0,
        });
      if (i == 0) {
        this.start = time;
      } else {
        vz = (alt - this.fi.pts[i - 1].alt) / ((time.getTime() - this.fi.pts[i - 1].time.getTime()) / 1000);
        if (vzm.length < 25) {
          vzm.push(vz);
        } else {
          if (j > 0 && j % 25 == 0) j = 0;
          vzm[j++] = vz;
        }
        vz = vzm.reduce((a, b) => a + b, 0) / vzm.length;
        this.fi.pts[i].vz = Math.round(vz * 10) / 10;
      }
      if (alt < this.fi.minalt) this.fi.minalt = alt;
      if (alt > this.fi.maxalt) this.fi.maxalt = alt;
      if (vz < this.fi.minvz) this.fi.minvz = vz;
      if (vz > this.fi.maxvz) this.fi.maxvz = vz;
    }
    // TODO : faire mieux (évaluer vz?)
    this.fi.minalt = Math.round(Math.max(0, this.fi.minalt));
    this.fi.maxalt = Math.round(Math.min(10000, this.fi.maxalt));
    this.fi.minvz = Math.round(Math.max(-15, this.fi.minvz) * 10) / 10;
    this.fi.maxvz = Math.round(Math.min(15, this.fi.maxvz) * 10) / 10;
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
}
