
class AnaTrace {
  analyse(data) {
    //http://192.168.1.16/web/logfly-web/src/trace.php?id=392
    const nbbearmean = 40;
    let imean = 0;
    let pt = data.pts[0];
    if (!pt) return;
    pt.diffbearing = 0;
    pt.diffvz = 0;
    let meanbear = [];
    let meanvz = [];
    for (let i=1; i<data.pts.length; i++) {
      pt = data.pts[i];
      // Bearing
      pt.diffbearing = Math.abs(pt.bearing-data.pts[i-1].bearing);
      if (pt.diffbearing > 180)
        pt.diffbearing = 360-pt.diffbearing;
      if (meanbear.length < nbbearmean)
        meanbear.push(pt.diffbearing);
      else {
        if (imean > 0 && (imean % nbbearmean) == 0) imean = 0;
        meanbear[imean++] = pt.diffbearing;
      }
      pt.diffbearing = Math.round(meanbear.reduce((a, b) => a + b, 0) / meanbear.length);
      // Vz
      pt.diffvz = pt.vz-data.pts[i-1].vz;
      if (meanvz.length < nbbearmean)
        meanvz.push(pt.diffvz);
      else {
        if (imean > 0 && (imean % nbbearmean) == 0) imean = 0;
        meanvz[imean++] = pt.diffvz;
      }
      pt.diffvz = meanvz.reduce((a, b) => a + b, 0) / meanvz.length;
    }
  }
}