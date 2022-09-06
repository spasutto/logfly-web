
class AnaTrace {
  analyse(data) {
    //http://192.168.1.16/web/logfly-web/src/trace.php?id=392
    const nbbearmean = 40;
    let imean = 0;
    let pt = data.pts[0];
    if (!pt) return;
    pt.diffbearing = 0;
    let bearmean = [];
    for (let i=1; i<data.pts.length; i++) {
      pt = data.pts[i];
      pt.diffbearing = Math.abs(pt.bearing-data.pts[i-1].bearing);
      if (pt.diffbearing > 180)
        pt.diffbearing = 360-pt.diffbearing;
      if (bearmean.length < nbbearmean)
        bearmean.push(pt.diffbearing);
      else {
        if (imean > 0 && (imean % nbbearmean) == 0) imean = 0;
        bearmean[imean++] = pt.diffbearing;
      }
      pt.diffbearing = Math.round(bearmean.reduce((a, b) => a + b, 0) / bearmean.length);
    }
  }
}