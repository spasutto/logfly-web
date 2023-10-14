function py2_round(value) {
  // Google's polyline algorithm uses the same rounding strategy as Python 2, which is different from JS for negative values
  return Math.floor(Math.abs(value) + 0.5) * (value >= 0 ? 1 : -1);
}
function encodept(current, previous, factor) {
  current = py2_round(current * factor);
  previous = py2_round(previous * factor);
  var coordinate = (current - previous) * 2;
  if (coordinate < 0) {
    coordinate = -coordinate - 1
  }
  var output = '';
  while (coordinate >= 0x20) {
    output += String.fromCharCode((0x20 | (coordinate & 0x1f)) + 63);
    coordinate /= 32;
  }
  output += String.fromCharCode((coordinate | 0) + 63);
  return output;
}
function encode(coordinates, precision) {
  if (!coordinates.length) { return ''; }

  var factor = Math.pow(10, Number.isInteger(precision) ? precision : 5),
    output = encodept(coordinates[0][0], 0, factor) + encodept(coordinates[0][1], 0, factor);

  for (var i = 1; i < coordinates.length; i++) {
    var a = coordinates[i], b = coordinates[i - 1];
    output += encodept(a[0], b[0], factor);
    output += encodept(a[1], b[1], factor);
  }

  return output;
}
function decode(encodedPath, precision = 5) {
  const factor = Math.pow(10, precision);

  const len = encodedPath.length;

  // For speed we preallocate to an upper bound on the final length, then
  // truncate the array before returning.
  const path = new Array(Math.floor(encodedPath.length / 2));
  let index = 0;
  let lat = 0;
  let lng = 0;
  let pointIndex = 0;

  // This code has been profiled and optimized, so don't modify it without
  // measuring its performance.
  for (; index < len; ++pointIndex) {
    // Fully unrolling the following loops speeds things up about 5%.
    let result = 1;
    let shift = 0;
    let b = 0;
    do {
      // Invariant: "result" is current partial result plus (1 << shift).
      // The following line effectively clears this bit by decrementing "b".
      b = encodedPath.charCodeAt(index++) - 63 - 1;
      result += b << shift;
      shift += 5;
    } while (b >= 0x1f); // See note above.
    lat += result & 1 ? ~(result >> 1) : result >> 1;

    result = 1;
    shift = 0;
    do {
      b = encodedPath.charCodeAt(index++) - 63 - 1;
      result += b << shift;
      shift += 5;
    } while (b >= 0x1f);
    lng += result & 1 ? ~(result >> 1) : result >> 1;

    path[pointIndex] = [lat / factor, lng / factor];
  }
  // truncate array
  path.length = pointIndex;

  return path;
}

class GraphGPX {
  #analysers = [];
  #DEBUG = false;
  static get DEFAULT_CONF() {
    return {
      elevationservice:undefined,
      disablescrollzoom: false,
      showvz: false,
      showvx: false,
      showgr: false,
      showgndalt: false,
      colors: {
        background: "#EAF8C4",
        text: "#0f0f0f",
        selection: "#55FF9C8F",
        axis: "#5f5f5f",
        axissecondary: "#afafaf",
        axistertiary: "#e5e5e5",
        alt: "#002fff",
        vz: "#af00af",
        vx: "#22af00",
        gr: "#2200af",
        gndalt: "#ff0000",
        debug: "#ff0000"
      }
    }
  }
 
  constructor(elem, options) {
    this.resetInfos();
    this.options = {...GraphGPX.DEFAULT_CONF, ...options};
    this.elem = elem;
    this.options.disablescrollzoom = this.options.disablescrollzoom === true;
    if (typeof this.options.elevationservice !== 'string' || this.options.elevationservice.trim().length <= 0) {
      this.options.elevationservice = undefined;
    }
    this.options.showvz = this.options.showvz === true;
    this.options.showvx = this.options.showvx === true;
    this.options.showgr = this.options.showgr === true;
    this.options.showgndalt = this.options.showgndalt === true;
    this.bdrect = this.elem.getBoundingClientRect();
    this.resetInfos();
    this.createCanvas();
  }

  set showVz(showvz) {
    this.options.showvz = showvz === true;
    this.paint();
  }
  get showVz() {
    return this.options.showvz === true;
  }

  set showVx(showvx) {
    this.options.showvx = showvx === true;
    this.paint();
  }
  get showVx() {
    return this.options.showvx === true;
  }

  set showGR(showgr) {
    this.options.showgr = showgr === true;
    this.paint();
  }
  get showGR() {
    return this.showgr.showgr === true;
  }

  set showGndAlt(showgndalt) {
    this.options.showgndalt = showgndalt === true;
    this.paint();
  }
  get showGndAlt() {
    return this.options.showgndalt === true;
  }

  set disableScrollZoom(disablescrollzoom) {
    this.options.disablescrollzoom = disablescrollzoom === true;
  }
  get disableScrollZoom() {
    return this.options.disablescrollzoom === true;
  }

  addAnalyser(analyser) {
    this.#analysers.push(analyser);
  }

  setDebugMode() {
    this.#DEBUG = true;
    this.paint();
  }

  resetInfos() {
    // à chaque changement impacter aussi fizoom dans updateZoom()
    this.fi = { 'pts': [], maxalt: -1000, minalt: 100000, totaltgain: 0, maxvz:-1000, minvz:100000, maxvx:-1000, minvx:100000, maxgr:-1000, mingr:100000, minaltdiff:100000, maxaltdiff:-1000, minlat:190, maxlat:-190, minlon:190, maxlon:-190, start: new Date };
    //this.fizoom = JSON.parse(JSON.stringify(this.fi)); // clone ne fonctionne pas pour la date
    this.elevcalls = 0;
    this.starttouch = 0;
    this.endtouch = 0;
    this.firstmovetouch = 0;
    this.isselecting = false;
    this.selectionpossible = !('ontouchstart' in document.documentElement); // sur les desktop selection toujours possible
    this.curidx = -1;
    this.selection = [-1,-1];
    this.zoomsel = [-1,-1];
    this.updateZoom();
  }
  
  updateZoom() {
    if (this.zoomsel[0]>-1 && this.zoomsel[1]>-1) {
      this.fizoom.pts = this.fi.pts.slice(this.zoomsel[0], this.zoomsel[1]);
      let fpt = this.fizoom.pts[0];
      this.fizoom.minalt = this.fizoom.pts.reduce((prev, cur) => prev<cur.alt?prev:cur.alt, fpt.alt);
      this.fizoom.maxalt = this.fizoom.pts.reduce((prev, cur) => prev>cur.alt?prev:cur.alt, fpt.alt);
      this.fizoom.minaltdiff = this.fizoom.pts.reduce((prev, cur) => prev<(cur.alt-cur.gndalt)?prev:cur.alt-cur.gndalt, fpt.alt-fpt.gndalt);
      this.fizoom.maxaltdiff = this.fizoom.pts.reduce((prev, cur) => prev>(cur.alt-cur.gndalt)?prev:cur.alt-cur.gndalt, fpt.alt-fpt.gndalt);
      this.fizoom.minvx = this.fizoom.pts.reduce((prev, cur) => prev<cur.vx?prev:cur.vx, fpt.vx);
      this.fizoom.maxvx = this.fizoom.pts.reduce((prev, cur) => prev>cur.vx?prev:cur.vx, fpt.vx);
      this.fizoom.minvz = this.fizoom.pts.reduce((prev, cur) => prev<cur.vz?prev:cur.vz, fpt.vz);
      this.fizoom.maxvz = this.fizoom.pts.reduce((prev, cur) => prev>cur.vz?prev:cur.vz, fpt.vz);
      this.fizoom.minlat = this.fizoom.pts.reduce((prev, cur) => prev<cur.lat?prev:cur.lat, fpt.lat);
      this.fizoom.maxlat = this.fizoom.pts.reduce((prev, cur) => prev>cur.lat?prev:cur.lat, fpt.lat);
      this.fizoom.minlon = this.fizoom.pts.reduce((prev, cur) => prev<cur.lon?prev:cur.lon, fpt.lon);
      this.fizoom.maxlon = this.fizoom.pts.reduce((prev, cur) => prev>cur.lon?prev:cur.lon, fpt.lon);
      this.fizoom.start = fpt.time;
    } else {
      this.fizoom = {};
      Object.keys(this.fi).forEach((function(k)
      {
        if (Array.isArray(this.fi[k]))
          this.fizoom[k] = this.fi[k].slice();
        else if (typeof this.fi[k].getMonth === 'function')
          this.fizoom[k] = new Date(this.fi[k]);
        else
          this.fizoom[k] = this.fi[k];
      }).bind(this));
    }
  }

  createCanvas() {
    this.canvas = document.createElement("canvas");
    let elem = document.createTextNode('Navigateur obsolète!');
    this.canvas.appendChild(elem);

    this.canvas.oncontextmenu = function(){return false;};
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
    this.canvas2.oncontextmenu = function(){return false;};
    this.canvas2.addEventListener('mousemove', this.mousemove.bind(this));
    this.canvas2.addEventListener('mousedown', this.mousedown.bind(this));
    this.canvas2.addEventListener('mouseup', this.mouseup.bind(this));
    this.canvas2.addEventListener('mouseleave', this.mouseleave.bind(this));
    this.canvas2.addEventListener('click', this.click.bind(this, false));
    this.canvas2.addEventListener('dblclick', this.click.bind(this, true));
    this.canvas2.addEventListener('wheel', this.wheel.bind(this), {capture: false});
    if ('ontouchstart' in document.documentElement) {
      this.canvas2.addEventListener("touchstart", this.touchevts.bind(this), true);
      this.canvas2.addEventListener("touchmove", this.touchevts.bind(this), true);
      this.canvas2.addEventListener("touchend", this.touchevts.bind(this), true);
    }

    elem = document.createElement("button");
    elem.style.position = 'absolute';
    elem.style.bottom = '5px';
    elem.style.right = '5px';
    elem.style.fontWeight = 'bolder';
    elem.style.userSelect = "none";
    elem.appendChild(document.createTextNode('\u2699'));
    elem.onclick = this.opencfg;
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
    elem.style.userSelect = "none";

    let elem2 = document.createElement("input");
    elem2.setAttribute('type', 'checkbox');
    elem2.id = 'grphshowvz';
    if (this.options.showvz) {
      elem2.checked = true;
    }
    elem2.onclick = function (evt) {
      this.options.showvz = evt.currentTarget.checked;
      this.paint();
    }.bind(this);
    elem.appendChild(elem2);
    elem2 = document.createElement("label");
    elem2.setAttribute('for', 'grphshowvz');
    elem2.setAttribute('title', 'vario');
    elem2.setAttribute('style', 'font-weight: bold;color: '+this.options.colors.vz);
    elem2.appendChild(document.createTextNode('Vz'));
    elem.appendChild(elem2);
    this.elem.appendChild(elem);

    elem2 = document.createElement("input");
    elem2.setAttribute('type', 'checkbox');
    elem2.id = 'grphshowvx';
    if (this.options.showvx) {
      elem2.checked = true;
    }
    elem2.onclick = function (evt) {
      this.options.showvx = evt.currentTarget.checked;
      this.paint();
    }.bind(this);
    elem.appendChild(elem2);
    elem2 = document.createElement("label");
    elem2.setAttribute('for', 'grphshowvx');
    elem2.setAttribute('title', 'vitesse sol');
    elem2.setAttribute('style', 'font-weight: bold;color: '+this.options.colors.vx);
    elem2.appendChild(document.createTextNode('Vx'));
    elem.appendChild(elem2);
    this.elem.appendChild(elem);

    elem2 = document.createElement("input");
    elem2.setAttribute('type', 'checkbox');
    elem2.id = 'grphshowgr';
    if (this.options.showgr) {
      elem2.checked = true;
    }
    elem2.onclick = function (evt) {
      this.options.showgr = evt.currentTarget.checked;
      this.paint();
    }.bind(this);
    elem.appendChild(elem2);
    elem2 = document.createElement("label");
    elem2.setAttribute('for', 'grphshowgr');
    elem2.setAttribute('title', 'finesse/glide ratio');
    elem2.setAttribute('style', 'font-weight: bold;color: '+this.options.colors.gr);
    elem2.appendChild(document.createTextNode('GR'));
    elem.appendChild(elem2);
    this.elem.appendChild(elem);

    elem2 = document.createElement("input");
    elem2.setAttribute('type', 'checkbox');
    elem2.id = 'grphshowgndalt';
    if (this.options.showgndalt) {
      elem2.checked = true;
    }
    elem2.onclick = function (evt) {
      this.options.showgndalt = evt.currentTarget.checked;
      this.paint();
    }.bind(this);
    elem.appendChild(elem2);
    elem2 = document.createElement("label");
    elem2.setAttribute('for', 'grphshowgndalt');
    elem2.setAttribute('title', 'altitude au dessus du sol');
    elem2.setAttribute('style', 'font-weight: bold;color: '+this.options.colors.gndalt);
    elem2.appendChild(document.createTextNode('alt AGL'));
    elem.appendChild(elem2);
    this.elem.appendChild(elem);

    this.paint();
  }

  addEventListener(evtname, fct) {
    this.elem.addEventListener(evtname, fct, {passive: true});
  }

  fitCanvas() {
    this.canvas2.width  = this.canvas.width  = this.canvas.offsetWidth;
    this.canvas2.height = this.canvas.height = this.canvas.offsetHeight;
    this.paint();
    this.paintmouseinfos();
  }

  touchevts(e) {
    this.opencfg(true);
    let theTouch = e.changedTouches[0];
    let mouseEv;

    switch(e.type)
    {
      case "touchstart":
        mouseEv = "mousedown";
        this.xtouchdown = e.touches[0].pageX;
        this.endtouch = this.starttouch = Date.now();
        this.selectionpossible = false;
        break;
      case "touchend":
        mouseEv = "mouseup";
        this.endtouch = Date.now();
        this.firstmovetouch = 0;
        break;
      case "touchmove":
        mouseEv="mousemove";
        break;
      default:
        return;
    }

    if (e.type == "touchmove" && this.firstmovetouch == 0) {
      if (Math.abs(e.touches[0].pageX - this.xtouchdown) > 3)
        this.firstmovetouch = Date.now();
      if (this.firstmovetouch - this.starttouch > 500) {
        this.selectionpossible = true;
        this.isselecting = true;
        this.selection[1] = this.selection[0] = this.curidx;
      }
    }
    if (e.type == "touchend") {
      this.selectionchanged(0, 0);
      if (this.endtouch - this.starttouch < 250) {
        mouseEv = "click";
      }
    }

    if (e.touches.length > 0 && typeof e.touches[0].pageX === 'number') {
      this.curidx = this.indexforx(e.touches[0].pageX);
      if (this.curidx > this.fizoom.pts.length-1) {
        this.curidx = this.fizoom.pts.length-1;
      }
      let curpt = this.fizoom.pts[this.curidx];
      let event = new CustomEvent('onposchanged', {"detail": curpt});
      this.elem.dispatchEvent(event);
    }

    let mouseEvent = document.createEvent("MouseEvent");
    mouseEvent.initMouseEvent(mouseEv, true, true, window, 1, theTouch.screenX, theTouch.screenY, theTouch.clientX, theTouch.clientY, false, false, false, false, 0, null);
    theTouch.target.dispatchEvent(mouseEvent);

    e.preventDefault();
  }

  wheel(e) {
    if (this.options.disablescrollzoom) return;
    let event = new CustomEvent('onwheel', {"detail": e.deltaY});
    this.elem.dispatchEvent(event);
    e.preventDefault();
  }

  click(dbl, e) {
    this.opencfg(true);
    if (!Array.isArray(this.fizoom.pts) || this.fizoom.pts.length <= 0)
      return;
    let x = e.clientX - this.bdrect.left;
    this.curidx = this.indexforx(x);
    if (this.curidx > this.fizoom.pts.length-1) {
      this.curidx = this.fizoom.pts.length-1;
      x = this.xforindex(this.curidx);
    }
    let curpt = this.fizoom.pts[this.curidx];
    let event = new CustomEvent(dbl?'ondblclick':'onclick', {"detail": curpt});
    this.elem.dispatchEvent(event);
  }

  mousemove(e) {
    if (!Array.isArray(this.fizoom.pts) || this.fizoom.pts.length <= 0)
      return;
    let x = e.clientX - this.bdrect.left;
    this.curidx = this.indexforx(x);
    if (this.curidx > this.fizoom.pts.length-1) {
      this.curidx = this.fizoom.pts.length-1;
      x = this.xforindex(this.curidx);
    }
    if (this.isselecting)
      this.selection[1] = this.curidx;
    this.paintmouseinfos();
    let curpt = this.fizoom.pts[this.curidx];
    let event = new CustomEvent('onposchanged', {"detail": curpt});
    this.elem.dispatchEvent(event);
  }
  
  mousedown(e) {
    if (this.selectionpossible)
      this.isselecting = true;
    if (!Array.isArray(this.fizoom.pts) || this.fizoom.pts.length <= 0)
      return;
    let x = e.clientX - this.bdrect.left;
    this.curidx = this.indexforx(x);
    if (this.curidx > this.fizoom.pts.length-1)
      this.curidx = this.fizoom.pts.length-1;
    if (this.selectionpossible)
      this.selection[1] = this.selection[0] = this.curidx;
    if (e.which == 3)
      this.zoomsel[0] = this.curidx;
    this.paintmouseinfos();
  }
  
  mouseup(e) {
    this.isselecting = false;
    let x = e.clientX - this.bdrect.left;
    this.curidx = this.indexforx(x);
    if (this.curidx > this.fizoom.pts.length-1)
      this.curidx = this.fizoom.pts.length-1;
    if (e.which == 3) {
      this.selection[0] = this.selection[1] = -1;
      this.zoomsel[1] = this.curidx;
      this.zoom();
    } else if (this.selectionpossible) {
      this.selection[1] = this.curidx;
      let startx = Math.min(this.selection[0], this.selection[1]),
        endx = Math.max(this.selection[0], this.selection[1]);
      this.selectionchanged(startx, endx);
    }
    this.paintmouseinfos();
  }
  
  mouseleave(e) {
    if (this.isselecting) {
      this.isselecting = false;
      this.selection[1] = this.selection[0] = -1;
      this.paintmouseinfos(this.curidx);
    }
  }
  
  selectionchanged(startx, endx) {
    let event = new CustomEvent('onselectionchanged', {"detail": [startx, endx]});
    this.elem.dispatchEvent(event);
  }

  indexforx(x) {
    return Math.round(x/this.incx)*this.incr;
  }

  xforindex(idx) {
    return Math.round(idx/this.incr)*this.incx;
  }

  opencfg(closecfg) {
    let pp = document.getElementById('grphcfig');
    if (typeof closecfg != 'boolean') {
      closecfg = pp.style.display == 'block';
    }
    pp.style.display = closecfg ? 'none' : 'block';
  }

  paintmouseinfos() {
    this.ctx2.clearRect(0, 0, this.canvas2.width, this.canvas2.height);

    if (this.selectionpossible && this.selection[0] != this.selection[1]) {
      let startx = this.xforindex(Math.min(this.selection[0], this.selection[1])),
        endx = this.xforindex(Math.max(this.selection[0], this.selection[1]));
      this.ctx2.fillStyle = this.options.colors.selection;
      this.ctx2.fillRect(startx, 0, endx-startx, this.canvas2.height-1);
    }
    if (this.curidx > -1 && this.curidx <= this.fizoom.pts.length-1) {
      let x = this.xforindex(this.curidx);
      this.ctx2.lineWidth = 1;
      this.ctx2.beginPath();
      this.ctx2.moveTo(x,0);
      this.ctx2.lineTo(x,this.canvas2.height);
      this.ctx2.stroke();
      this.ctx2.fillStyle = "#FFFF9C8F";
      this.ctx2.fillRect(this.canvas2.width-70, 0, this.canvas2.width, 60);
      let curpt = this.fizoom.pts[this.curidx];
      this.ctx2.font = '10px sans-serif';
      this.ctx2.fillStyle = this.options.colors.axis;
      let posx=this.canvas2.width - 70, posy = 0;
      this.ctx2.fillText(curpt.alt + ' m AMSL', posx, posy+=10);
      if (typeof curpt.gndalt == 'number')
        this.ctx2.fillText(Math.round(curpt.alt-curpt.gndalt)+' m AGL', posx, posy+=10);
      this.ctx2.fillStyle = this.options.colors.vz;
      this.ctx2.fillText(curpt.vz + ' m/s', posx, posy+=10);
      this.ctx2.fillStyle = this.options.colors.vx;
      let vxtext = curpt.vx + ' km/h';
      this.ctx2.fillText(vxtext, posx, posy+=10);
      let rvxtextw = this.ctx2.measureText(vxtext+' ').width;
      this.ctx2.save();
      this.ctx2.textAlign="center";
      this.ctx2.textBaseline="middle";
      this.ctx2.translate(posx+rvxtextw+5,posy-4);
      this.ctx2.rotate(curpt.bearing * (Math.PI / 180));
      this.ctx2.font = '15px sans-serif';
      this.ctx2.fillText('\u21e7' , 0, 0);// 2b99 2191 21A5 21E7 21EB 21EC 25B2 032D 1403 1431
      this.ctx2.restore();
      if (this.#DEBUG) {
      this.ctx2.fillStyle = this.options.colors.debug;
        this.ctx2.fillText(curpt.diffbearing+'°' , posx+rvxtextw+12, posy);
      }
      let gr = curpt.gr;
      if (gr === Infinity) {
        gr = '\u221E';
      }
      this.ctx2.fillText('finesse : ' + gr, posx, posy+=10);
      let t = new Date(Date.UTC(1970, 0, 1));
      t.setUTCSeconds((curpt.time.getTime() - this.fizoom.start.getTime()) / 1000);
      this.ctx2.fillStyle = this.options.colors.axis;
      this.ctx2.fillText(curpt.time.toLocaleString('fr-FR'/*, { timeZone: 'UTC' }*/).substr(-8, 5) + " ("+t.toLocaleString('fr-FR', { timeZone: 'UTC' }).substr(-8, 5)+")", posx, posy+=10);
    }
  }

  paint() {
    this.ctx.fillStyle = this.options.colors.background;
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.strokeStyle = this.options.colors.axis;
    this.ctx.lineWidth = 1;
    if (!Array.isArray(this.fizoom.pts) || this.fizoom.pts.length <= 0) {
      this.ctx.font = '18px sans-serif';
      this.ctx.fillText('chargement...', (this.canvas.width / 2) - 20, (this.canvas.height / 2) - 5);
      return;
    }
    let defaultfont = '10px sans-serif';
    this.ctx.font = defaultfont;
    let t = 0, x = 0, y = 0;
    let minaltg = Math.floor(this.fizoom.minalt / 100) * 100;
    let maxaltg = Math.ceil(this.fizoom.maxalt / 100) * 100;
    if (maxaltg == minaltg) maxaltg += 500;
    //console.log(this.fizoom.minalt, this.fizoom.maxalt, minaltg, maxaltg, this.fizoom.pts[0]);
    let altdiff = maxaltg - minaltg;//this.fizoom.maxalt-this.fizoom.minalt;
    altdiff *= 1.05;
    let maxvx = Math.ceil(this.fizoom.maxvx);
    let coefh = this.canvas.height / altdiff;
    let coefhvz = this.canvas.height / (this.fizoom.maxvz-this.fizoom.minvz);
    let coefhvx = this.canvas.height / (maxvx-this.fizoom.minvx);
    let maxgr = Math.min(20, this.fizoom.maxgr);
    let coefhgr = this.canvas.height / (maxgr-this.fizoom.mingr);
    let coefhgndalt = this.canvas.height / (this.fizoom.maxaltdiff-this.fizoom.minaltdiff);
    let getY = function (alt) { return this.canvas.height - Math.round(coefh * (alt - this.fizoom.minalt)); }.bind(this);
    let getYVz = function (vz) { return this.canvas.height - Math.round(coefhvz * (vz - this.fizoom.minvz)); }.bind(this);
    let getYVx = function (vx) { return this.canvas.height - Math.round(coefhvx * (vx - this.fizoom.minvx)); }.bind(this);
    let getYGR = function (gr) { if (gr === Infinity) gr=maxgr; return this.canvas.height - Math.round(coefhgr * (gr - this.fizoom.mingr)); }.bind(this);
    let getYGndAlt = function (altdiff) { return this.canvas.height - Math.round(coefhgndalt * (altdiff - this.fizoom.minaltdiff)); }.bind(this);
    this.incx = this.canvas.width / this.fizoom.pts.length;
    this.incr = 1;
    if (this.incx < 1) {
      this.incr = 1 / this.incx;
      this.incx = 1;
    }
    this.incx = Math.floor(this.incx);
    this.incr = Math.ceil(this.incr);

    // grille de fond alt
    this.ctx.strokeStyle = this.options.colors.axistertiary;
    this.ctx.lineWidth = 1;
    minaltg = Math.floor(minaltg / 500) * 500;
    for (t = minaltg; t <= maxaltg+500; t += 500) {
      y = getY(t);
      if (y <= 0 || y > this.canvas.height) continue;
      this.ctx.beginPath();
      this.ctx.moveTo(0, y);
      this.ctx.lineTo(this.canvas.width, y);
      this.ctx.stroke();
    }

    // heures (barres)
    let firsthour = new Date(this.fizoom.start); firsthour.setMilliseconds(0); firsthour.setSeconds(0); firsthour.setMinutes(0);
    firsthour = 3600 - ((this.fizoom.start - firsthour) / 1000);
    let secstotal = (this.fizoom.pts[this.fizoom.pts.length - 1].time - this.fizoom.start) / 1000;
    let inct = (secstotal / (this.incx * this.fizoom.pts.length)) / this.incr;
    this.ctx.strokeStyle = this.options.colors.axissecondary;
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
    if (typeof this.fizoom.pts[0].gndalt == 'number') {
      this.ctx.fillStyle = this.options.colors.axissecondary;
      this.ctx.strokeStyle = this.options.colors.axis;
      x = 0;
      y = getY(this.fizoom.pts[0].gndalt);
      this.ctx.beginPath();
      this.ctx.moveTo(0, y);
      for (t = 0; t < this.fizoom.pts.length; t += this.incr) {
        y = getY(this.fizoom.pts[t].gndalt);
        y = Math.min(this.canvas.height, y);
        if (y >= 0) {
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

    // heures (texte)
    this.ctx.font = '9px sans-serif';
    this.ctx.fillStyle  = this.options.colors.text;
    x = 0;
    for (t = firsthour; t < secstotal; t += 3600) {
      x = Math.round(inct * t);
      let h = this.pad(new Date(this.fizoom.start.getTime() + t*1000).getHours(), 2) + ':00';
      this.ctx.fillText(h, Math.max(0, x-11), this.canvas.height-1);
    }
    this.ctx.font = defaultfont;

    this.ctx.strokeStyle = this.options.colors.alt;

    // alt
    x = 0;
    y = getY(this.fizoom.pts[0].alt);
    this.ctx.beginPath();
    this.ctx.moveTo(x, y);
    for (t = 0; t < this.fizoom.pts.length; t += this.incr) {
      y = getY(this.fizoom.pts[t].alt);
      y = Math.min(this.canvas.height, y);
      if (y >= 0) {
        this.ctx.lineTo(x, y);
      }
      x += this.incx;
    }
    this.ctx.stroke();

    // vz
    if (this.options.showvz) {
      this.ctx.strokeStyle = this.options.colors.vz;
      x = 0;
      y = getYVz(this.fizoom.pts[0].vz);
      this.ctx.beginPath();
      this.ctx.moveTo(x, y);
      for (t = 0; t < this.fizoom.pts.length; t += this.incr) {
        y = getYVz(this.fizoom.pts[t].vz);
        y = Math.min(this.canvas.height, y);
        if (y >= 0) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.stroke();
    }

    // vx
    if (this.options.showvx) {
      this.ctx.strokeStyle = this.options.colors.vx;
      x = 0;
      y = getYVx(this.fizoom.pts[0].vx);
      this.ctx.beginPath();
      this.ctx.moveTo(x, y);
      for (t = 0; t < this.fizoom.pts.length; t += this.incr) {
        y = getYVx(this.fizoom.pts[t].vx);
        y = Math.min(this.canvas.height, y);
        if (y >= 0) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.stroke();
    }

    // GR (finesse)
    if (this.options.showgr) {
      this.ctx.strokeStyle = this.options.colors.gr;
      x = 0;
      y = getYGR(this.fizoom.pts[0].gr);
      this.ctx.beginPath();
      this.ctx.moveTo(x, y);
      for (t = 0; t < this.fizoom.pts.length; t += this.incr) {
        y = getYGR(this.fizoom.pts[t].gr);
        y = Math.min(this.canvas.height, y);
        if (y >= 0) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.stroke();
    }

    // gndalt
    if (this.options.showgndalt) {
      this.ctx.strokeStyle = this.options.colors.gndalt;
      x = 0;
      y = getYGndAlt(this.fizoom.pts[0].alt-this.fizoom.pts[0].gndalt);
      this.ctx.beginPath();
      this.ctx.moveTo(x, y);
      for (t = 0; t < this.fizoom.pts.length; t += this.incr) {
        y = getYGndAlt(this.fizoom.pts[t].alt-this.fizoom.pts[t].gndalt);
        y = Math.min(this.canvas.height, y);
        if (y >= 0) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.stroke();
    }

    if (this.#DEBUG) {
      let bklw = this.ctx.lineWidth;
      // diff bearing
      //this.ctx.lineWidth = 2;
      let coefhbearing = this.canvas.height / 45;
      let getYBearing = function (bearing) { return this.canvas.height - Math.round(coefhbearing * (bearing)); }.bind(this);
      this.ctx.strokeStyle = this.options.colors.debug;
      x = 0;
      y = getYBearing(this.fizoom.pts[0].diffbearing);
      this.ctx.beginPath();
      this.ctx.moveTo(x, y);
      for (t = 0; t < this.fizoom.pts.length; t += this.incr) {
        y = getYBearing(this.fizoom.pts[t].diffbearing);
        y = Math.min(this.canvas.height, y);
        if (y >= 0) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.stroke();
      // diff vz
      let mindiffvz = this.fizoom.pts.reduce((prev, cur) => prev<cur.diffvz?prev:cur.diffvz, 0);
      let maxdiffvz = this.fizoom.pts.reduce((prev, cur) => prev>cur.diffvz?prev:cur.diffvz, 0);
      coefhvz = this.canvas.height / (maxdiffvz-mindiffvz);
      getYVz = function (vz, mindiffvz) { return this.canvas.height - Math.round(coefhvz * (vz - mindiffvz)); }.bind(this);
      this.ctx.strokeStyle = 'black';
      x = 0;
      y = getYVz(this.fizoom.pts[0].diffvz, mindiffvz);
      this.ctx.beginPath();
      this.ctx.moveTo(x, y);
      for (t = 0; t < this.fizoom.pts.length; t += this.incr) {
        y = getYVz(this.fizoom.pts[t].diffvz, mindiffvz);
        y = Math.min(this.canvas.height, y);
        if (y >= 0) {
          this.ctx.lineTo(x, y);
        }
        x += this.incx;
      }
      this.ctx.stroke();
      this.ctx.lineWidth = bklw;
    }

    this.ctx.strokeStyle = this.options.colors.axis;
    this.ctx.lineWidth = 2;
    // legende alt
    this.ctx.beginPath();
    this.ctx.moveTo(0, 0);
    this.ctx.lineTo(0, this.canvas.height);
    this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
    this.ctx.stroke();
    this.ctx.fillStyle = this.options.colors.text;
    minaltg = Math.floor(minaltg / 500) * 500;
    let firsthourx = Math.round(inct * firsthour);
    for (t = minaltg; t <= maxaltg+500; t += 500) {
      y = getY(t);
      if (y <= 0 || y > this.canvas.height) continue;
      // pas de dessin par dessus le texte de la première heure
      if (y>this.canvas.height-8 && firsthourx<35) continue;
      this.ctx.beginPath();
      this.ctx.moveTo(0, y);
      this.ctx.lineTo(4, y);
      this.ctx.stroke();
      this.ctx.fillText(t, 5, y + 3);
    }

    let minscale = 33;
    // legende vz
    if (this.options.showvz) {
      this.ctx.strokeStyle = this.options.colors.axissecondary;
      this.ctx.fillStyle = this.options.colors.vz;
      this.ctx.lineWidth = 1;
      this.ctx.beginPath();
      this.ctx.moveTo(minscale, 0);
      this.ctx.lineTo(minscale, this.canvas.height);
      this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
      this.ctx.stroke();
      //this.ctx.fillStyle = "#0f0f0f";
      let maxvz = Math.ceil(this.fizoom.maxvz);
      let minvz = Math.floor(this.fizoom.minvz);
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
    if (this.options.showvx) {
      this.ctx.strokeStyle = this.options.colors.axissecondary;
      this.ctx.fillStyle = this.options.colors.vx;
      this.ctx.lineWidth = 1;
      this.ctx.beginPath();
      this.ctx.moveTo(minscale, 0);
      this.ctx.lineTo(minscale, this.canvas.height);
      this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
      this.ctx.stroke();
      //this.ctx.fillStyle = "#0f0f0f";
      let minvx = Math.floor(this.fizoom.minvx);
      let step = Math.round((maxvx-minvx)/6); // 6 graduations dans la hauteur
      let divis = Math.pow(10, step.toString().length - 1);
      step = Math.max(10, Math.round(step/divis)*divis);
      minvx = Math.round(minvx/divis)*divis;
      for (t = minvx; t <= maxvx; t+=step) {
        y = getYVx(t);
        if (y <= 0 || y > this.canvas.height) continue;
        this.ctx.beginPath();
        this.ctx.moveTo(minscale, y - 3);
        this.ctx.lineTo(minscale+4, y - 3);
        this.ctx.stroke();
        this.ctx.fillText(t, minscale+5, y);
      }
      minscale += 20;
    }

    // legende GR
    if (this.options.showgr) {
      this.ctx.strokeStyle = this.options.colors.axissecondary;
      this.ctx.fillStyle = this.options.colors.gr;
      this.ctx.lineWidth = 1;
      this.ctx.beginPath();
      this.ctx.moveTo(minscale, 0);
      this.ctx.lineTo(minscale, this.canvas.height);
      this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
      this.ctx.stroke();
      //this.ctx.fillStyle = "#0f0f0f";
      //let maxgr = Math.ceil(this.fizoom.maxgr);
      let mingr = Math.floor(this.fizoom.mingr);
      for (t = mingr; t <= maxgr; t+=5) {
        y = getYGR(t);
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

    // legende alt AGL
    if (this.options.showgndalt) {
      this.ctx.strokeStyle = this.options.colors.axissecondary;
      this.ctx.fillStyle = this.options.colors.gndalt;
      this.ctx.lineWidth = 1;
      this.ctx.beginPath();
      this.ctx.moveTo(minscale, 0);
      this.ctx.lineTo(minscale, this.canvas.height);
      this.ctx.lineTo(this.canvas.width - 1, this.canvas.height);
      this.ctx.stroke();
      //this.ctx.fillStyle = "#0f0f0f";
      let maxaltdiff = Math.ceil(this.fizoom.maxaltdiff);
      let minaltdiff = Math.floor(this.fizoom.minaltdiff);
      let step = Math.round((maxaltdiff-minaltdiff)/6); // 6 graduations dans la hauteur
      let divis = Math.pow(10, step.toString().length - 1);
      step = Math.max(1, Math.round(step/divis)*divis);
      minaltdiff = Math.round(minaltdiff/divis)*divis;
      for (t = minaltdiff; t <= maxaltdiff; t+=step) {
        y = getYGndAlt(t);
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
  }

  zoom(reset) {
    reset = reset === true || this.zoomsel[0] >= this.zoomsel[1];
    if (reset)
      this.zoomsel = [-1,-1];
    this.updateZoom();
    this.paint();
    this.paintmouseinfos();
    let fpt = this.fizoom.pts[0];
    let event = new CustomEvent('onzoom', { "detail": {
      'minlat' : this.fizoom.minlat,
      'maxlat' : this.fizoom.maxlat,
      'minlon' : this.fizoom.minlon,
      'maxlon' : this.fizoom.maxlon
    } });
    this.elem.dispatchEvent(event);
  }

  setData(points) {
    // les vz max/min sont en instantané donc très grandes
    const VZMAX = 30;
    const VZMIN = -30;
    this.resetInfos();
    let i = 0, j = 0, k = 0, alt = 0, lat = 0, lon = 0, latvx = 0, lonvx = 0, vz = 0, vx = 0, gr = 0, tdiff = 0, vzm = [], vxm = [];
    let time;
    for (i=0; i<points.length; i++) {
      alt = points[i].alt;
      time = points[i].time;
      lat = points[i].lat;
      lon = points[i].lon;
      this.fi.pts.push(
        {
          'lat': lat,
          'lon': lon,
          'alt': alt,
          //'gndalt': 0, // todo voir pourquoi bug, parfois un élément du tableau n'a pas de gndalt
          'time': time,
          'vz': 0,
          'vx': 0,
          'bearing': 0,
          'gr': 0,
        });
      if (i == 0) {
        this.fi.start = new Date(time);
        latvx = lat;
        lonvx = lon;
      } else {
        tdiff = (time.getTime() - this.fi.pts[i - 1].time.getTime()) / 1000;
        tdiff = (tdiff === 0 || isNaN(tdiff) || tdiff === Infinity) ? 1 : tdiff;
        vz = (alt - this.fi.pts[i - 1].alt) / tdiff;
        // si VZ > max alors on réévalue alt avec VZ -antérieur- ne fonctionne pas, on prends vz = 0 et l'altitude du point précédent
        if (vz > VZMAX || vz < VZMIN) {
          vz = 0;//Math.max(VZMIN, Math.min(VZMAX, vz));//this.fi.pts[i - 1].vz
          //this.fi.pts[i].alt = alt = this.fi.pts[i - 1].alt;//vz * tdiff + this.fi.pts[i - 1].alt;
        }
        if (vzm.length < 25) {
          vzm.push(vz);
        } else {
          if (j > 0 && j % 25 == 0) j = 0;
          vzm[j++] = vz;
        }
        vz = vzm.reduce((a, b) => a + b, 0) / vzm.length;
        this.fi.pts[i].vz = Math.round(vz * 10) / 10;

        vx = 3.6 * GraphGPX.distance(lat, lon, this.fi.pts[i - 1].lat, this.fi.pts[i - 1].lon) / tdiff;
        if (vxm.length < 15) {
          vxm.push(vx);
        } else {
          if (k > 0 && k % 15 == 0) k = 0;
          vxm[k++] = vx;
        }
        vx = Math.round(vxm.reduce((a, b) => a + b, 0) / vxm.length);
        this.fi.pts[i].vx = vx;
        this.fi.pts[i].bearing = Math.round(GraphGPX.bearing(this.fi.pts[i - 1].lat, this.fi.pts[i - 1].lon, lat, lon));
        gr = Infinity;
        if (vz<0) {
          let curvz = -1 * vz;
          gr = Math.round((vx / (3.6*curvz))*10)/10;
        }
        if (gr > 99) {
          gr = Infinity;
        }
        this.fi.pts[i].gr = gr;
      }
      if (alt != 0) {
        if (alt < this.fi.minalt) this.fi.minalt = alt;
        if (alt > this.fi.maxalt) this.fi.maxalt = alt;
        let diffalt = 0;
        if (i > 0 && (diffalt=(alt - this.fi.pts[i - 1].alt)) > 0) this.fi.totaltgain += diffalt;
      }
      if (vz < this.fi.minvz) this.fi.minvz = vz;
      if (vz > this.fi.maxvz) this.fi.maxvz = vz;
      if (vx < this.fi.minvx) this.fi.minvx = vx;
      if (vx > this.fi.maxvx) this.fi.maxvx = vx;
      if (gr < this.fi.mingr) this.fi.mingr = gr;
      if (gr > this.fi.maxgr) this.fi.maxgr = gr;
      if (lat < this.fi.minlat) this.fi.minlat = lat;
      if (lat > this.fi.maxlat) this.fi.maxlat = lat;
      if (lon < this.fi.minlon) this.fi.minlon = lon;
      if (lon > this.fi.maxlon) this.fi.maxlon = lon;
    }
    // TODO : faire mieux (évaluer vz?)
    this.fi.minalt = Math.max(0, this.fi.minalt);
    this.fi.maxalt = Math.min(10000, this.fi.maxalt);
    this.fi.minvz = Math.max(-15, this.fi.minvz);
    this.fi.maxvz = Math.min(15, this.fi.maxvz);
    this.fi.minvx = Math.max(0, this.fi.minvx);
    this.fi.maxvx = Math.min(200, this.fi.maxvx);
    if (typeof this.options.elevationservice === 'string') {
      let curelev = 0;
      let locations = [];
      for (let i = 0; i < this.fi.pts.length; i++) {
        locations.push(this.fi.pts[i].lat);
        locations.push(this.fi.pts[i].lon);
        if (i && i % 9999 == 0) {
          this.getElevation(locations, curelev, 10000);
          curelev = i + 1;
          locations = [];
        }
      }
      if (curelev < this.fi.pts.length)
        this.getElevation(locations, curelev, this.fi.pts.length - curelev);
    } else {
      let event = new CustomEvent('ondataloaded', { "detail": this.fi });
      this.elem.dispatchEvent(event);
    }
    this.updateZoom();
    this.paint();
    this.paintmouseinfos();
    if (Array.isArray(this.#analysers)) {
      this.#analysers.forEach(a => a.analyse(this.fi));
    }
    return this.fi;
  }

  getElevation(locations, index, count) {
    var xhttp = new XMLHttpRequest();
    /*locations = locations.reduce((arr, cur, i) => {if (i%2==0) arr.push([cur]); else arr[arr.length-1].push(cur); return arr;}, []);
    let data = {
      //"locations": locations,
      "locations": encode(locations, 6),
      "doInfills": false,
      "interpolate": false
    };*/
    // Float64Array pour double float
    // attention au conflits d'endianness, getElevation.php décode en little endian
    let data = new Float32Array(locations);
    xhttp.responseType = 'text';
    let minusalt = 0;
    xhttp.onreadystatechange = function() {
      if (xhttp.readyState == 4 && xhttp.status == 200) {
        if (!xhttp.responseText) return;
        try {
          //let dv = (new DataView(new Uint8Array(xhttp.responseText.split('').map(v => v.charCodeAt(0))).buffer));
          //let alts = JSON.parse(xhttp.responseText);
          let altdiff = 0;
          for (let i=index,j=0; i < index+count; i++,j+=2) {
            //this.fi.pts[i].gndalt = alts[j++];
            //this.fi.pts[i].gndalt = (j+2>dv.byteLength) ? 0 : dv.getInt16(j, true);
            this.fi.pts[i].gndalt = (j+2>xhttp.responseText.length) ? 0 : (new DataView(new Uint8Array([xhttp.responseText.charCodeAt(j), xhttp.responseText.charCodeAt(j+1)]).buffer)).getInt16(0, true);
            if (this.fi.pts[i].alt == 0)
              this.fi.pts[i].alt = this.fi.pts[i].gndalt;
            altdiff = this.fi.pts[i].alt - this.fi.pts[i].gndalt;
            if (altdiff < this.fi.minaltdiff) this.fi.minaltdiff = altdiff;
            if (altdiff > this.fi.maxaltdiff) this.fi.maxaltdiff = altdiff;
          }
          // réalignement sur l'altitude d'atterissage ; dangereux si la trace est coupée, on se retrouve avec tout le vol sous terre
          //if (typeof this.fi.pts[this.fi.pts.length-1].gndalt == 'number')
          //  minusalt = this.fi.pts[this.fi.pts.length-1].alt - this.fi.pts[this.fi.pts.length-1].gndalt;
        }
        catch (e) { console.log("error \"" + e + "\" while eval " + xhttp.responseText); }
        if (minusalt != 0)
          this.fi.pts.forEach(function (pt) { pt.alt -= minusalt; });
        this.fi.maxalt = this.arrayMax(this.fi.pts, 'alt');
        this.fi.minalt = this.arrayMin(this.fi.pts, 'alt');
        this.fi.minalt = Math.max(0, this.fi.minalt);
        this.fi.maxalt = Math.min(10000, this.fi.maxalt);
        this.fi.minaltdiff = Math.max(0, this.fi.minaltdiff);
        this.fi.maxaltdiff = Math.min(10000, this.fi.maxaltdiff);
        this.elevcalls--;
        if (this.elevcalls <= 0) {
          this.updateZoom();
          this.paint();
          let event = new CustomEvent('ondataloaded', {"detail": this.fi});
          this.elem.dispatchEvent(event);
        }
      }
    }.bind(this);
    xhttp.open("POST", this.options.elevationservice, true);
    //xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    //xhttp.send(JSON.stringify(data));
    xhttp.overrideMimeType("text/plain; charset=x-user-defined");
    xhttp.send(data);
    this.elevcalls++;
  }
  
  setPos(pt) {
    this.curidx = this.fizoom.pts.findIndex(p => p.time == pt.time);
    this.paintmouseinfos();
  }

  arrayMin(arr, prop) {
    var len = arr.length, min = Infinity;
    while (len--) {
      if (arr[len][prop] < min) {
        min = arr[len][prop];
      }
    }
    return min;
  }

  arrayMax(arr, prop) {
    var len = arr.length, max = -Infinity;
    while (len--) {
      if (arr[len][prop] > max) {
        max = arr[len][prop];
      }
    }
    return max;
  }
  
  pad(num, size) {
    num = num.toString();
    while (num.length < size) num = "0" + num;
    return num;
  }
  
  toCSV() {
    let csvContent = "data:application/octet-stream,";
    csvContent += Object.keys(graph.fi.pts[0]).join(";") + "\n";
    csvContent += graph.fi.pts.map(pt => Object.keys(pt).map(k =>
    {
      if (!Object.hasOwn(pt, k) || typeof pt[k] === 'undefined')
        return "";
      else if (typeof pt[k].getMonth === 'function')
        return '"' + GraphGPX.formatDate(pt[k]) + '"';
      else if (typeof pt[k] === 'string')
        return '"' + pt[k].replaceAll('"', '""') + '"';
      return pt[k];
    }).join(";")).join("\n");
    window.open(encodeURI(csvContent));
  }
  
  static formatDate(dt) {
    var options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    return dt.toLocaleDateString("fr-FR", options);
  }
  
  // Converts from degrees to radians.
  static toRadians(degrees) {
    return degrees * Math.PI / 180;
  }
   
  // Converts from radians to degrees.
  static toDegrees(radians) {
    return radians * 180 / Math.PI;
  }

  // Calculates the great-circle distance between two points, with the Haversine formula.
  static distance(latitudeFrom, longitudeFrom, latitudeTo, longitudeTo, earthRadius = 6371000) {
    let latFrom = GraphGPX.toRadians(latitudeFrom);
    let lonFrom = GraphGPX.toRadians(longitudeFrom);
    let latTo = GraphGPX.toRadians(latitudeTo);
    let lonTo = GraphGPX.toRadians(longitudeTo);

    let latDelta = latTo - latFrom;
    let lonDelta = lonTo - lonFrom;

    let angle = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin(latDelta / 2), 2) +
    Math.cos(latFrom) * Math.cos(latTo) * Math.pow(Math.sin(lonDelta / 2), 2)));
    return angle * earthRadius;
  }

  // Calculates the bearing between two points
  static bearing(startLat, startLng, destLat, destLng){
    startLat = GraphGPX.toRadians(startLat);
    startLng = GraphGPX.toRadians(startLng);
    destLat = GraphGPX.toRadians(destLat);
    destLng = GraphGPX.toRadians(destLng);

    let y = Math.sin(destLng - startLng) * Math.cos(destLat);
    let x = Math.cos(startLat) * Math.sin(destLat) -
          Math.sin(startLat) * Math.cos(destLat) * Math.cos(destLng - startLng);
    let brng = Math.atan2(y, x);
    brng = GraphGPX.toDegrees(brng);
    return (brng + 360) % 360;
  }
}