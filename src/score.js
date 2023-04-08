
var scoringrule = 'FFVL';

function score(igccontent, cbk) {
    IGCScore.score(igccontent, (score) => {
      if (score && typeof score.value == 'object') {
        score = score.value;
      }
      if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
      let rules = IGCScore.xcScoringRules[scoringrule].find(sr => sr.name == score.opt.scoring.name);
      if (rules && typeof rules.closingDistance === 'function') {
        score.closingCircleRadius = rules.closingDistance(score.scoreInfo.distance, {'scoring':rules}) * 1000;
      }
      cbk(score);
    }, scoringrule, 60);
}


/*


          IGCScore.score(this.responseText, (score) => {
            message("");
            if (score && typeof score.value == 'object') {
              score = score.value;
            }
            if (score && typeof score.opt == 'object' && typeof score.opt.flight == 'object') delete score.opt.flight;
            if (confirm ("Le score calculé est de " + Math.round(score.score*10)/10 + " points pour "+Math.round(score.scoreInfo.distance*10)/10+"km, mettre à jour?")) {
              message("enregistrement...");
              postFlightScore(id, score, (msg) => {message("");alert(msg == "OK"?"Fait!":"Il semble qu'il y'ai eu un problème : " + msg);});
            }
          }, 'FFVL', 60);
          
          */