class GraphGPX {

  constructor(elem) {
    this.pts = [];
    this.maxalt = -1000;
    this.minalt = 100000;
    this.elem = elem;
    this.createCanvas();
  }

  get area() {
    return this.calcArea();
  }

  calcArea() {
    return this.elem;
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
  
  mousemove(e) {
    if (!Array.isArray(this.pts) || this.pts.length <= 0)
      return;
    let rect = this.elem.getBoundingClientRect(),
        x = e.clientX - rect.left,
        idxpt = Math.round(x/this.incx)*this.incr;
    if (idxpt > this.pts.length-1) {
      idxpt = this.pts.length-1;
      x = Math.round((idxpt / this.incr) * this.incx);
    }
    this.ctx2.clearRect(0, 0, this.canvas2.width, this.canvas2.height);
    this.ctx2.lineWidth = 1;
    this.ctx2.beginPath();
    this.ctx2.moveTo(x,0);
    this.ctx2.lineTo(x,this.canvas2.height);
    this.ctx2.stroke();
    this.ctx2.fillStyle = "#FFFF9C8F";
    this.ctx2.fillRect(this.canvas2.width-50, 0, this.canvas2.width, 30);
    let curpt = this.pts[idxpt];
    this.ctx2.font = '10px sans-serif';
    this.ctx2.fillStyle = "#5f5f5f";
    this.ctx2.fillText(curpt.alt+' m', this.canvas2.width-50, 10);
    this.ctx2.fillText(curpt.time.toLocaleString('fr-FR'/*, { timeZone: 'UTC' }*/).substr(-8, 5), this.canvas2.width-50, 20);
    let onposchanged = new CustomEvent('onposchanged', {"detail": curpt});
    this.elem.dispatchEvent(onposchanged);
  }
  
  paint() {
    this.ctx.fillStyle = "#EAF8C4";
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    if (!Array.isArray(this.pts) || this.pts.length <= 0)
      return;
    let t=0;
    let minmil = Math.floor(this.minalt/500)*500;
    let maxmil = Math.floor(this.maxalt/500)*500;
    if (maxmil == minmil) maxmil+=500;
    //console.log(this.minalt, this.maxalt, minmil, maxmil, this.pts[0]);
    let altdiff = maxmil-minmil;//this.maxalt-this.minalt;
    //altdiff *= 1.05;
    let coefh = this.canvas.height/altdiff;
    let getY = function(alt) {return this.canvas.height-Math.round(coefh*(alt-this.minalt));}.bind(this);
    this.incx = this.canvas.width/this.pts.length;
    this.incr = 1;
    if (this.incx < 1)
    {
      this.incr = 1/this.incx;
      this.incx = 1;
    }
    this.incx = Math.floor(this.incx);
    this.incr = Math.ceil(this.incr);

    this.ctx.lineWidth = 2;
    this.ctx.beginPath();
    this.ctx.moveTo(0,0);
    this.ctx.lineTo(0,this.canvas.height);
    this.ctx.lineTo(this.canvas.width-1,this.canvas.height);
    this.ctx.stroke();
    this.ctx.lineWidth = 1;

    this.ctx.fillStyle = "#5f5f5f";
    this.ctx.font = '10px sans-serif';
    let x = 0, y=getY(this.pts[0].alt);
    for (t=minmil; t<=maxmil; t+=500) {
      y=getY(t);
      if (y<=0 || y>this.canvas.height) continue;
      this.ctx.beginPath();
      this.ctx.moveTo(0,y-3);
      this.ctx.lineTo(4,y-3);
      this.ctx.stroke();
      this.ctx.fillText(t, 5, y);
    }
    this.ctx.beginPath();
    this.ctx.moveTo(0,y);
    for (t=0; t<this.pts.length; t+=this.incr) {
      y = getY(this.pts[t].alt);
      this.ctx.lineTo(x+=this.incx,y);
    }
    this.ctx.stroke();
    //this.ctx.fillText(this.pts.length+' pts, incx='+incx+', incr='+this.incr, 10, 10);
  }
  
  setGPX(gpx) {
    let xpts = gpx.getElementsByTagName("trkpt");
    this.pts = [];
    this.maxalt = -1000;
    this.minalt = 100000;
    let alt = 0;
    let time = 0;
    for (let i=0; i<xpts.length; i++) {
      alt = parseInt(xpts[i].getElementsByTagName("ele")[0].textContent);
      time = new Date(xpts[i].getElementsByTagName("time")[0].textContent);
      this.pts.push(
      {
        'lat' : xpts[i].getAttribute('lat'),
        'lon' : xpts[i].getAttribute('lon'),
        'alt' : alt,
        'time' : time,
      });
      if (alt < this.minalt) this.minalt = alt;
      if (alt > this.maxalt) this.maxalt = alt;
      //if (i>150) break;
    }
    this.paint();
  }
}
