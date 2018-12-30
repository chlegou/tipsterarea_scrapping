<?php
/**
 * Created by PhpStorm.
 * User: Chlegou
 * Date: 30/12/2018
 * Time: 10:58
 */


// the script may take several minutes due to IO requests to fetch data, so we increment timeout
if(!ini_get('safe_mode')){
    set_time_limit(600);// seconds
}else{
    echo "safe mode is on! please disable it".PHP_EOL;
}

// Include the phpQuery library
// Download at http://code.google.com/p/phpquery/
include 'libs/phpQuery.php';

// path to save in
$path = 'data/';
$dateOffset = date('Ymd_His');
$fileName = 'data_'.$dateOffset.'.txt';

# Website URL
$url = "https://tipsterarea.com/";
/** Groups Pattern: $countryName - $tournamentName
 * we look only for the first "-" !! (with spaces maybe)
 * Exclude: National Friendly / Club Friendly
 * each $tournamentName should contain games
 * each game is like this format: $team0 - $team1
 * $team0:home - $team1:away
 * Links to gather information for teams:
 * $countryName and $teamX should be lower cases for url, and spaces replaced with "-" !!
 * Home/Away:   "https://tipsterarea.com/teams/$countryName/$teamX/last-50"
 * Home:        "https://tipsterarea.com/teams/$countryName/$teamX/last-home"
 * Away:        "https://tipsterarea.com/teams/$countryName/$teamX/last-away"
 */
$last50_url = "https://tipsterarea.com/teams/{{countryName}}/{{teamX}}/last-50";
$home_url = "https://tipsterarea.com/teams/{{countryName}}/{{teamX}}/last-home";
$away_url = "https://tipsterarea.com/teams/{{countryName}}/{{teamX}}/last-away";


// return the html content from URL,could be used for local and remote files
function getURLContent($url){
    $doc = new DOMDocument;
    $doc->preserveWhiteSpace = FALSE;
    @$doc->loadHTMLFile($url);
    return $doc->saveHTML();
}

$html = getURLContent($url);
// Create phpQuery document with returned HTML
$doc = phpQuery::newDocument($html);


$gameGroups = pq('#contentContainer > table.games');
//$gameGroups = array_slice($gameGroups->elements, 14, 1); // useful for debugging
//print_r($gameGroups);
foreach ($gameGroups as $gameGroupNode){
    $gameGroup = pq($gameGroupNode);

    $gameGroupTitle = trim($gameGroup->find('thead  th.left')->text());

    // skip " *** friendly" games
    if (preg_match('/(friendly)/i', $gameGroupTitle)) {
        continue;
    }
    //echo $gameGroupTitle .PHP_EOL;
    $gameGroupTitleSplit = preg_split('/( - .*?)/', $gameGroupTitle, PREG_SPLIT_DELIM_CAPTURE);
    //print_r($gameGroupTitleSplit);
    $countryName = strtolower($gameGroupTitleSplit[0]);
    $countryName_stripped = str_replace(' ', '-', $countryName);
    $tournamentName = strtolower($gameGroupTitleSplit[1]);

    $games = $gameGroup->find('tbody td:nth-child(2) a');
    foreach ($games as $gameNode){
        $game = pq($gameNode);
        $gameTeams = trim($game->text());
        //echo $gameTeams .PHP_EOL;
        $gameTeamsSplit = preg_split('/( - .*?)/', $gameTeams, PREG_SPLIT_DELIM_CAPTURE);
        //print_r($gameTeamsSplit);

        $team0 = $gameTeamsSplit[0];
        $team1 = $gameTeamsSplit[1];

        $team0_stripped = str_replace(' ', '-', strtolower($gameTeamsSplit[0]));
        $team1_stripped = str_replace(' ', '-', strtolower($gameTeamsSplit[1]));
        // echo $team0_stripped .'||'. $team1_stripped.PHP_EOL;

        // team0_last50
        $goalsScoredByHomeTotal = 0; // 1
        $goalsSufferedByHomeTotal = 0; // 2
        $drawGamesByHomeTotal = 0; // 9

        // team1_last50
        $goalsScoredByAwayTotal = 0; // 3
        $goalsSufferedByAwayTotal = 0; // 4
        $drawGamesByAwayTotal = 0; // 10

        // team0_home
        $goalsScoredByHomeHomeOnly = 0; // 5
        $goalsSufferedByHomeHomeOnly = 0; // 6
        $drawGamesByHomeHomeOnly = 0; // 11

        // team1_away
        $goalsScoredByAwayAwayOnly = 0;  // 7
        $goalsSufferedByAwayAwayOnly = 0; // 8
        $drawGamesByAwayAwayOnly = 0; // 12

        //echo str_replace(['{{countryName}}', '{{teamX}}'], [$countryName_stripped, $team0_stripped], $last50_url).PHP_EOL;
        $team0_last50_html = getURLContent(str_replace(['{{countryName}}', '{{teamX}}'], [$countryName_stripped, $team0_stripped], $last50_url));
        $doc = phpQuery::newDocument($team0_last50_html);
        $team0_last50_games = array_slice(pq('table.gamesStat tbody tr')->elements, 0, 5);
        foreach ($team0_last50_games as $team0_gameNode){
            $team0_game = pq($team0_gameNode);
            //echo $team0_game->html();

            $tournament = strtolower(trim($team0_game->find('td.tournament')->text()));
            // skip " *** friendly" games
            if (preg_match('/(friendly)/i', $tournament)) {
                continue;
            }

            $matchScoreComponent = $team0_game->find('td.result');
            if($matchScoreComponent->hasClass('draw')){
                $drawGamesByHomeTotal++;
            }

            $matchScore = preg_split('/( - .*?)/', trim($matchScoreComponent->text()), PREG_SPLIT_DELIM_CAPTURE);
            //print_r($matchScore);
            $score0 = intval($matchScore[0]);
            $score1 = intval($matchScore[1]);

            $homeTeam = strtolower(trim($team0_game->find('td.homeTeam')->text()));
            if(preg_match("/($team0)/i", $homeTeam)){
                $goalsScoredByHomeTotal += $score0;
                $goalsSufferedByHomeTotal += $score1;
            }else{
                $goalsScoredByHomeTotal += $score1;
                $goalsSufferedByHomeTotal += $score0;
            }

        }
        //echo $team0 .'|' .$goalsScoredByHomeTotal .'|' .$goalsSufferedByHomeTotal .'|'.$drawGamesByHomeTotal .'|' .PHP_EOL;



        $team1_last50_html = getURLContent(str_replace(['{{countryName}}', '{{teamX}}'], [$countryName_stripped, $team1_stripped], $last50_url));
        $doc = phpQuery::newDocument($team1_last50_html);
        $team1_last50_games = array_slice(pq('table.gamesStat tbody tr')->elements, 0, 5);
        foreach ($team1_last50_games as $team1_gameNode){
            $team1_game = pq($team1_gameNode);
            //echo $team1_game->html();

            $tournament = strtolower(trim($team1_game->find('td.tournament')->text()));
            // skip " *** friendly" games
            if (preg_match('/(friendly)/i', $tournament)) {
                continue;
            }

            $matchScoreComponent = $team1_game->find('td.result');
            if($matchScoreComponent->hasClass('draw')){
                $drawGamesByAwayTotal++;
            }

            $matchScore = preg_split('/( - .*?)/', trim($matchScoreComponent->text()), PREG_SPLIT_DELIM_CAPTURE);
            //print_r($matchScore);
            $score0 = intval($matchScore[0]);
            $score1 = intval($matchScore[1]);

            $homeTeam = strtolower(trim($team1_game->find('td.homeTeam')->text()));
            if(preg_match("/($team1)/i", $homeTeam)){
                $goalsScoredByAwayTotal += $score0;
                $goalsSufferedByAwayTotal += $score1;
            }else{
                $goalsScoredByAwayTotal += $score1;
                $goalsSufferedByAwayTotal += $score0;
            }

        }
        //echo $team1 .'|' .$goalsScoredByAwayTotal .'|' .$goalsSufferedByAwayTotal .'|'.$drawGamesByAwayTotal .'|' .PHP_EOL;



        $team0_home_html = getURLContent(str_replace(['{{countryName}}', '{{teamX}}'], [$countryName_stripped, $team0_stripped], $home_url));
        $doc = phpQuery::newDocument($team0_home_html);
        $team0_home_games = array_slice(pq('table.gamesStat tbody tr')->elements, 0, 5);
        foreach ($team0_home_games as $team0_gameNode){
            $team0_game = pq($team0_gameNode);
            //echo $team0_game->html();

            $tournament = strtolower(trim($team0_game->find('td.tournament')->text()));
            // skip " *** friendly" games
            if (preg_match('/(friendly)/i', $tournament)) {
                continue;
            }

            $matchScoreComponent = $team0_game->find('td.result');
            if($matchScoreComponent->hasClass('draw')){
                $drawGamesByHomeHomeOnly++;
            }

            $matchScore = preg_split('/( - .*?)/', trim($matchScoreComponent->text()), PREG_SPLIT_DELIM_CAPTURE);
            //print_r($matchScore);
            $score0 = intval($matchScore[0]);
            $score1 = intval($matchScore[1]);

            $goalsScoredByHomeHomeOnly += $score0;
            $goalsSufferedByHomeHomeOnly += $score1;

        }
        //echo $team0 .'|' .$goalsScoredByHomeHomeOnly .'|' .$goalsSufferedByHomeHomeOnly .'|'.$drawGamesByHomeHomeOnly .'|' .PHP_EOL;



        $team1_away_html = getURLContent(str_replace(['{{countryName}}', '{{teamX}}'], [$countryName_stripped, $team1_stripped], $away_url));
        $doc = phpQuery::newDocument($team1_away_html);
        $team1_away_games = array_slice(pq('table.gamesStat tbody tr')->elements, 0, 5);
        foreach ($team1_away_games as $team1_gameNode){
            $team1_game = pq($team1_gameNode);
            //echo $team1_game->html();

            $tournament = strtolower(trim($team1_game->find('td.tournament')->text()));
            // skip " *** friendly" games
            if (preg_match('/(friendly)/i', $tournament)) {
                continue;
            }

            $matchScoreComponent = $team1_game->find('td.result');
            if($matchScoreComponent->hasClass('draw')){
                $drawGamesByAwayAwayOnly++;
            }

            $matchScore = preg_split('/( - .*?)/', trim($matchScoreComponent->text()), PREG_SPLIT_DELIM_CAPTURE);
            //print_r($matchScore);
            $score0 = intval($matchScore[0]);
            $score1 = intval($matchScore[1]);

            $homeTeam = strtolower(trim($team1_game->find('td.homeTeam')->text()));
            $goalsScoredByAwayAwayOnly += $score1;
            $goalsSufferedByAwayAwayOnly += $score0;

        }
        //echo $team1 .'|' .$goalsScoredByAwayAwayOnly .'|' .$goalsSufferedByAwayAwayOnly .'|'.$drawGamesByAwayAwayOnly .'|' .PHP_EOL;



        $record = $countryName .'|' .$tournamentName .'|' .$team0 .':'.$team1 .'|';
        $record .= $goalsScoredByHomeTotal .'|' .$goalsSufferedByHomeTotal .'|' .$goalsScoredByAwayTotal .'|'.$goalsSufferedByAwayTotal .'|';
        $record .= $goalsScoredByHomeHomeOnly .'|' .$goalsSufferedByHomeHomeOnly .'|' .$goalsScoredByAwayAwayOnly .'|'.$goalsSufferedByAwayAwayOnly .'|';
        $record .= $drawGamesByHomeTotal .'|' .$drawGamesByAwayTotal .'|' .$drawGamesByHomeHomeOnly .'|'.$drawGamesByAwayAwayOnly ;

        // save to txt file
        file_put_contents($path . $fileName, $record. PHP_EOL, FILE_APPEND | LOCK_EX);

        // prints results
        echo $record. PHP_EOL;
    }




    //echo $gameGroup->html();
}