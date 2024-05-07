class Bounds {
  constructor(value) {
    if (value instanceof Bounds) {
      this.min = value.min;
      this.max = value.max;
    } else if (value instanceof Array) {
      if (value.length == 2) {
        this.min = value[0];
        this.max = value[1];
      } else {
        this.min = value[0];
        this.max = value[0];
        for (let i2 = 0; i2 < value.length; i2++) {
          if (value[i2] < this.min)
            this.min = value[i2];
          if (value[i2] > this.max)
            this.max = value[i2];
        }
      }
    } else {
      this.min = value;
      this.max = value;
    }
  }
  static createbounds(value) {
    if (value instanceof Array && value.length == 0) {
      return null;
    } else if (value == null) {
      return null;
    }
    return new Bounds(value);
  }
  update(value) {
    if (value instanceof Bounds) {
      if (value.min < this.min)
        this.min = value.min;
      if (value.max > this.max)
        this.max = value.max;
    } else {
      if (value < this.min)
        this.min = value;
      if (value > this.max)
        this.max = value;
    }
  }
  tuple() {
    return [this.min, this.max];
  }
}
const R = 6371e3;
class AnaTrace {
  get name() {
    return "Analyse trace";
  }
  get show() {
    return this._show;
  }
  set show(value) {
    this._show = !!value;
  }
  constructor(show=false) {
    this._show = show;
    this.bounds = {};
    this.s = [0];
    this.alt = [];
    this.total_dz_positive = 0;
    this.max_dz_positive = 0;
    this.min_ele = 0;
    this.speed = [];
    this.climb = [];
    this.tec = [];
    this.progress = [];
    this.thermals = [];
    this.glides = [];
    this.dives = [];
  }
  static distance_to(from, to) {
    let d2 = Math.sin(from.lat) * Math.sin(to.lat) + Math.cos(from.lat) * Math.cos(to.lat) * Math.cos(from.lon - to.lon);
    return d2 < 1 ? R * Math.acos(d2) : 0;
  }
  static interpolate(other, delta) {
    let d2 = Math.sin(this.lat) * Math.sin(other.lat) + Math.cos(this.lat) * Math.cos(other.lat) * Math.cos(other.lon - this.lon);
    d2 = d2 < 1 ? delta * Math.acos(d2) : 0;
    let y2 = Math.sin(other.lon - this.lon) * Math.cos(other.lat);
    let x2 = Math.cos(this.lat) * Math.sin(other.lat) - Math.sin(this.lat) * Math.cos(other.lat) * Math.cos(other.lon - this.lon);
    let theta = Math.atan2(y2, x2);
    let lat = Math.asin(Math.sin(this.lat) * Math.cos(d2) + Math.cos(this.lat) * Math.sin(d2) * Math.cos(theta));
    let lon = this.lon + Math.atan2(Math.sin(theta) * Math.sin(d2) * Math.cos(this.lat), Math.cos(d2) - Math.sin(this.lat) * Math.sin(lat));
    let alt = (1 - delta) * this.alt + delta * other.alt;
    return {"lat":lat,"lon":lon,"alt":alt};
  }
  static runs(seq) {
    let indexes = [];
    let start = 0, index = 0;
    let current = seq[0];
    let element;
    for (index = 0; index < seq.length; index++) {
      element = seq[index];
      if (element != current) {
        indexes.push({ start, stop: index });
        start = index;
        current = element;
      }
    }
    indexes.push({ start, stop: index });
    return indexes;
  }
  static runs_where(seq) {
    let indexes = [];
    let start = 0, index = 0;
    let current = seq[0];
    let element;
    for (index = 0; index < seq.length; index++) {
      element = seq[index];
      if (element != current) {
        if (current) {
          indexes.push({ start, stop: index });
        }
        start = index;
        current = element;
      }
    }
    if (current) {
      indexes.push({ start, stop: index });
    }
    return indexes;
  }
  static condense(ranges, t2, delta) {
    let indexes = [];
    if (ranges.length > 0) {
      let sl = ranges[0];
      let start = sl.start;
      let stop = sl.stop;
      for (let i2 = 0; i2 < ranges.length; i2++) {
        sl = ranges[i2];
        if (t2[sl.start] - t2[stop] < delta) {
          stop = sl.stop;
        } else {
          indexes.push({ start, stop });
          start = sl.start;
          stop = sl.stop;
        }
      }
      indexes.push({ start, stop });
    }
    return indexes;
  }
  analyse(data) {
    //data.pts
    //{"lat":44.445455,"lon":6.371385,"alt":1333,"time":"2024-04-14T13:37:10.108Z","vz":1.5,"vx":30,"bearing":273,"gr":null}
    let dt2 = 20;
    let n2 = data.pts.length;
    let period = (data.pts[n2 - 1].time.getTime() - data.pts[0].time.getTime()) / 1e3 / n2;
    if (dt2 < 2 * period)
      dt2 = 2 * period;
    this.t = data.pts.map((c2) => c2.time.getTime() / 1e3);
    this.bounds["ele"] = Bounds.createbounds(data.pts.map((c2) => c2.alt));
    this.bounds["time"] = Bounds.createbounds([data.pts[0].time.getTime() / 1e3, data.pts[n2 - 1].time.getTime() / 1e3]);
    this.bounds["t"] = Bounds.createbounds([this.t[0], this.t[n2 - 1]]);
    if (this.bounds["ele"] && (this.bounds["ele"].min != 0 || this.bounds["ele"].max != 0))
      this.elevation_data = true;
    this.min_ele = data.pts[0].alt;
    let dz = 0;
    for (let i2 = 1; i2 < n2; i2++) {
      this.s.push(this.s[i2 - 1] + AnaTrace.distance_to(data.pts[i2 - 1], data.pts[i2]));
      this.alt.push((data.pts[i2 - 1].alt + data.pts[i2 - 1].alt) / 2);
      dz = data.pts[i2].alt - data.pts[i2 - 1].alt;
      if (dz > 0)
        this.total_dz_positive += dz;
      if (data.pts[i2].alt < this.min_ele)
        this.min_ele = data.pts[i2].alt;
      else if (data.pts[i2].alt - this.min_ele > this.max_dz_positive)
        this.max_dz_positive = data.pts[i2].alt - this.min_ele;
    }
    let i0 = 0, i1 = 0, t0 = 0, t1 = 0, s0 = 0, s1 = 0, delta0 = 0, delta1 = 0, ds = 0, ds2 = 0, dp = 0, progress = 0;
    let coord0, coord1;
    for (let i2 = 1; i2 < n2; i2++) {
      t0 = (this.t[i2 - 1] + this.t[i2]) / 2 - dt2 / 2;
      while (this.t[i0] <= t0) {
        i0++;
      }
      if (i0 == 0) {
        coord0 = data.pts[0];
        s0 = this.s[0];
      } else {
        delta0 = (t0 - this.t[i0 - 1]) / (this.t[i0] - this.t[i0 - 1]);
        coord0 = AnaTrace.interpolate.bind(data.pts[i0 - 1])(data.pts[i0], delta0);
        s0 = (1 - delta0) * this.s[i0 - 1] + delta0 * this.s[i0];
      }
      t1 = t0 + dt2;
      while (i1 < n2 && this.t[i1] < t1) {
        i1++;
      }
      if (i1 == n2) {
        coord1 = data.pts[n2 - 1];
        s1 = this.s[n2 - 1];
      } else {
        delta1 = (t1 - this.t[i1 - 1]) / (this.t[i1] - this.t[i1 - 1]);
        coord1 = AnaTrace.interpolate.bind(data.pts[i1 - 1])(data.pts[i1], delta1);
        s1 = (1 - delta1) * this.s[i1 - 1] + delta1 * this.s[i1];
      }
      ds = s1 - s0;
      ds2 = s1 * s1 - s0 * s0;
      dz = coord1.alt - coord0.alt;
      dp = AnaTrace.distance_to(coord0, coord1);
      if (ds == 0) {
        progress = 0;
      } else if (dp > ds) {
        progress = 1;
      } else {
        progress = dp / ds;
      }
      this.speed.push(3.6 * ds / dt2);
      this.climb.push(dz / dt2);
      this.tec.push(dz / dt2 + ds2 / (2 * 9.80665));
      this.progress.push(progress);
    }
    this.bounds["speed"] = Bounds.createbounds(this.speed);
    this.bounds["climb"] = Bounds.createbounds(this.climb);
    this.bounds["tec"] = Bounds.createbounds(this.tec);
    let state = Array(n2 - 1).fill(0 /* UNKNOWN */);
    let glide = this.progress.map((p2) => p2 >= 0.9);
    let sl;
    let indexes = AnaTrace.condense(AnaTrace.runs_where(glide), this.t, 60);
    for (let i2 = 0; i2 < indexes.length; i2++) {
      sl = indexes[i2];
      for (let j2 = sl.start; j2 < sl.stop; j2++) {
        state[j2] = 2 /* GLIDE */;
      }
    }
    let dive = Array.from(Array(n2 - 1)).map((v2, i2) => this.progress[i2] < 0.9 && this.climb[i2] < 1);
    indexes = AnaTrace.condense(AnaTrace.runs_where(dive), this.t, 30);
    for (let i2 = 0; i2 < indexes.length; i2++) {
      sl = indexes[i2];
      if (data.pts[sl.stop].alt - data.pts[sl.start].alt >= -100)
        continue;
      for (let j2 = sl.start; j2 < sl.stop; j2++) {
        state[j2] = 3 /* DIVE */;
      }
    }
    let thermal = Array.from(Array(n2 - 1)).map((v2, i2) => this.progress[i2] < 0.9 && this.climb[i2] > 0 || this.speed[i2] < 10 && this.climb[i2] > 0 || this.climb[i2] > 1);
    indexes = AnaTrace.condense(AnaTrace.runs_where(thermal), this.t, 60);
    for (let i2 = 0; i2 < indexes.length; i2++) {
      sl = indexes[i2];
      for (let j2 = sl.start; j2 < sl.stop; j2++) {
        state[j2] = 1 /* THERMAL */;
      }
    }
    indexes = AnaTrace.runs(state);
    for (let i2 = 0; i2 < indexes.length; i2++) {
      sl = indexes[i2];
      dt2 = this.t[sl.stop] - this.t[sl.start];
      dz = data.pts[sl.stop].alt - data.pts[sl.start].alt;
      switch (state[sl.start]) {
        case 1 /* THERMAL */:
          if (dt2 >= 60 && dz > 50) {
            this.thermals.push(sl);
          }
          break;
        case 3 /* DIVE */:
          if (dt2 >= 30 && dz / dt2 < -2) {
            this.dives.push(sl);
          }
          break;
        case 2 /* GLIDE */:
          if (dt2 >= 120) {
            this.glides.push(sl);
          }
          break;
      }
    }
  }
  
  paint(grph) {
    let idxstartzoom = 0, idxendzoom = grph.fi.pts.length-1;
    if (grph.zoomsel[0]>-1 && grph.zoomsel[1]>-1) {
      idxstartzoom = grph.fi.pts.indexOf(grph.fizoom.pts[0]);
      idxendzoom = grph.fi.pts.indexOf(grph.fizoom.pts[grph.fizoom.pts.length-1]);
    }
    grph.ctx.globalAlpha = 0.2;
    this.thermals.forEach(t => {
      if (idxstartzoom >= t.stop || idxendzoom <= t.start) return;
      let tstart = t.start;
      let tstop = t.stop;
      if (grph.zoomsel[0]>-1 && grph.zoomsel[1]>-1) {
        tstart -= grph.zoomsel[0];
        tstop -= grph.zoomsel[0];
      }
      let xstart = grph.xforindex(Math.max(0, tstart));
      let xend = grph.xforindex(tstop);
      grph.ctx.fillStyle = "red";
      grph.ctx.fillRect(xstart, 0, xend-xstart, grph.canvas.height);
    });
    this.glides.forEach(g => {
      if (idxstartzoom >= g.stop || idxendzoom <= g.start) return;
      let gstart = g.start;
      let gstop = g.stop;
      if (grph.zoomsel[0]>-1 && grph.zoomsel[1]>-1) {
        gstart -= grph.zoomsel[0];
        gstop -= grph.zoomsel[0];
      }
      let xstart = grph.xforindex(Math.max(0, gstart));
      let xend = grph.xforindex(gstop);
      grph.ctx.fillStyle = "green";
      grph.ctx.fillRect(xstart, 0, xend-xstart, grph.canvas.height);
    });
    this.dives.forEach(d => {
      if (idxstartzoom >= d.stop || idxendzoom <= d.start) return;
      let dstart = d.start;
      let dstop = d.stop;
      if (grph.zoomsel[0]>-1 && grph.zoomsel[1]>-1) {
        dstart -= grph.zoomsel[0];
        dstop -= grph.zoomsel[0];
      }
      let xstart = grph.xforindex(Math.max(0, dstart));
      let xend = grph.xforindex(dstop);
      grph.ctx.fillStyle = "blue";
      grph.ctx.fillRect(xstart, 0, xend-xstart, grph.canvas.height);
    });
    grph.ctx.globalAlpha = 1;
  }
  paintmouseinfos(grph, x) {
    let idxpt = grph.fi.pts.indexOf(grph.fizoom.pts[grph.curidx]);
    if (idxpt < 0) return;
    if (this.thermals.some(t => t.start<=idxpt && t.stop>=idxpt)) {
      grph.ctx2.fillText('enroulage', x, 10);
    } else if (this.glides.some(g => g.start<=idxpt && g.stop>=idxpt)) {
      grph.ctx2.fillText('transition', x, 10);
    } else if (this.dives.some(d => d.start<=idxpt && d.stop>=idxpt)) {
      grph.ctx2.fillText('perte d\'altitude', x, 10);
    }
  }
}
