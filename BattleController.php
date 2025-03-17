<?php
date_default_timezone_set('America/Regina');
include "./controllers/UsuarioController.php";
include "./controllers/ItemController.php";

class BattleController{
    public function index($id){
        if(isset($_SESSION['user'])){
            if(isset($id) && !empty($id)){
                $Monster = new Monster();
                
                $mob_id_zone[] = Battle::getMobId($id); //gets id_monster from table monsters
                $mobId = $mob_id_zone[0]['id_monster']; //I assign it to a variable
    
                $Usuario = new Usuario($_SESSION['user']['id']);
                $Monster -> getMobStats($mobId, $id); // get mob stats from model
                $mobStatsArray[] = array(
                    "id" => $Monster->id,
                    "mob_zone_id" => $Monster->mob_zone_id,
                    "name" => $Monster->name,
                    "level" => $Monster->level,
                    "id_element" => $Monster->id_element,
                    "damage" => $Monster->damage,
                    "health" => $Monster->health,
                    "defense" => $Monster->defense,
                    "magical_defense" => $Monster->magical_defense,
                    "zone" => $Monster->zone,
                    "current_health" => $Monster->current_mob_health,
                    "current_mob_max_health" => $Monster->current_mob_max_health,
                );
                if($Monster->dead == 1){ // check if mob is dead
                    $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                    header('Location: '."{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}".$uri.'/zone/'.$Monster->mob_zone);// if dead, return player to monster´s zone
                }
    
                // $Usuario -> getPlayerStats();
                $playerStatsArray[] = array(
                    "strength" => $Usuario->strength,
                    "id_element" => $Usuario->idElement,
                    "element" => $Usuario->element
                );
    
                $mobHealthPercentage = $this->toPercentage($mobStatsArray[0]['current_mob_max_health'],$mobStatsArray[0]['current_health']);
    
                $allowBattle[] = Battle::allowBattle($id);
                $skills = Skills::findUserSkills($id);
                if($_SESSION['user']['level'] >= $allowBattle[0]['zone_level']){
                    view('battle.index', ["mobStats"=>$mobStatsArray,"playerStat"=> $playerStatsArray, "mobHealthPercentage"=>$mobHealthPercentage, "skills"=>$skills]);
                }else{
                    $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                    header('Location: '."{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}".$uri.'/zones');
                }
            }else{
                view("inventory.index", '');
            }
        }else{
            view("usuario.login", '');
        }
    }
    public function toPercentage($v1, $v2){ //function to calculate any percentage
        // * $v1 = max value, $v2 = min value
        $percentage = (($v2*100)/$v1);
        return round($percentage, 2).'%';
    }
    public function updateStats($id){
        $Monster = new Monster();
        $Usuario = new Usuario($_SESSION['user']['id']);
        $Monster -> getMobHealth($id);
        // $Usuario -> getPlayerStats();

        // * If mob current health is equal or below 0 it will display 0% and not a negative number
        if($Monster->current_mob_health <= 0){
            $updated_health_percentage = "0%";
        }else{
            $updated_health_percentage = $this->toPercentage($Monster->current_mob_max_health, $Monster->current_mob_health);
        }

        // * If mob current health is equal or below 0 it will display 0 and not a negative number
        if($Monster->current_mob_health <= 0){
            $current_mob_health = 0;
        }else{
            $current_mob_health = $Monster->current_mob_health;
        }

        $data = array(
            "current_mob_health" => $current_mob_health,
            "current_player_health" => $Usuario->current_health,
            "current_player_mana" => $Usuario->current_mana,
            "updated_health_percentage" => $updated_health_percentage
        );

        return $data;
    }
    public function updatePlayerStats(){
        $Usuario = new Usuario($_SESSION['user']['id']);
        // $Usuario->getPlayerStats();

        $data = array(
            "current_xp" => $Usuario->xp
        );
        return $data;
    }
    public function attack(){
        $color = "#ccc";
        if(checkCSRF($_SESSION['user']['csrf']))
        {
            $data = array(
                "status" => 500,
                "msg" => "Token CSRF no es válido."
            );
            
            echo json_encode($data);
            die();
        }
        $hour = date('h:i A');
        $Battle = new Battle();
        $Usuario = new Usuario($_SESSION['user']['id']);
        $Monster = new Monster();
        // $Usuario->getPlayerStats();
        $Monster->getMobStats($_REQUEST['hI'], $_REQUEST['hMI']);

        $player_damage = $Usuario->damage;
        $playerDefense = $Usuario->defense;

        // * Player damage
        $min_player_dmg = ceil($player_damage - ($player_damage * 0.02));
        $max_player_dmg = ceil($player_damage + ($player_damage * 0.02));

        $final_player_dmg = rand($min_player_dmg, $max_player_dmg);
        $mob_defense = $Monster->defense;
        
        $playerDamage = ($final_player_dmg*(100/(90+$mob_defense)));

        // * Calculating chances of missing hit
        $player_aim = $Usuario->aim;
        $player_dodge = $Usuario->dodge;
        $mob_level = $Monster->level;

        // * Calculate mob agility
        if($Monster->id_element == 4){ //if mob has air element has more agility
            $mob_agility = ceil($mob_level * 2);
        }elseif($Monster->id_element == 3){//if mob has earth element has less agility
            $mob_agility = ceil($mob_level);
        }else{
            $mob_agility = ceil($mob_level * 1.5);
        }
        $mob_dodge = ($mob_agility/2);
        $mob_aim = ($mob_agility/2);
        $mob_name = $Monster->name;

        // * Calculate player's hit chances
        $player_hit_chance = ((($player_aim*0.75)-($mob_dodge*0.45)))*100;
        if($player_hit_chance <= 0){//if hit chances are zero o below recalculate chances to give player a small chance to hit
            $player_hit_chance = 5;     
        }
        if($player_hit_chance >= 100){//if hit chances are 100 or above realculate to give player a small chance to miss hit
            //$player_hit_chance = 95; 
        }
        $rand_factor_hit_chance = rand(0,100);
        if($rand_factor_hit_chance < $player_hit_chance){

            // * Calculate player's critical attack chances
            $player_luck = $Usuario->luck;
            $player_critical = $Usuario->critical_damage;
            $critical_chance = ((($player_luck*0.1)+($player_critical*0.9))/500)*100;
            $rand_factor_critical_hit = rand(0,100);
            
            if($rand_factor_critical_hit < $critical_chance){ //if player hits a critical
                $lower_mob_health = ($Monster->current_mob_health)-ceil($playerDamage*1.50);
                $first_message = "<li style='color:#ff3333'>".$hour." - Has realizado: <span>".ceil($playerDamage*1.75)."</span> de daño con un golpe crítico!</li>";
                $log_message1 = "Has realizado: ".ceil($playerDamage*1.75)." de daño con golpe crítico.";
                $color = "#ff3333";
            }else{//if player doesnt hit a critical
                $lower_mob_health = ($Monster->current_mob_health)-ceil($playerDamage);
                $first_message = "<li style='color:#42A100'>".$hour." - Has realizado: <span>".ceil($playerDamage)."</span> de daño con un ataque básico</li>";
                $log_message1 = "Has realizado: ".ceil($playerDamage)." de daño con un ataque básico";
                $color = "#42A100";
            }

            // * Execute method for a successful player attack
            $Battle->player_attack($lower_mob_health,$_REQUEST['hMI']);
        }else{
            $first_message = "<li>".$hour." - ".$mob_name." ha esquivado tu ataque!</li>";
            $log_message1 = $mob_name." ha esquivado tu ataque!";
            $color = "#42A100";
        }
        $mob_damage = json_decode($this->mobDamage($_REQUEST['hI'], $_REQUEST['hMI']));

        $updated_stats = $this->updateStats($_REQUEST['hMI']);

        // * Kill Mob and his consequences
        $kill_message = "";
        $drops = "";

        BattleLog::log($log_message1, $color);
        BattleLog::log($mob_damage->log_msg, $mob_damage->color);
        
        if($updated_stats['current_mob_health'] <= 0){
            $kill_message = $this->killMob($_REQUEST['hMI'],$_SESSION['user']['id']);
            // * Drop items to player
            $drops = $this->dropItems($Monster->id);

            // * Fill zones if neccesary
            $this->fillZone($Monster->zone);
        }

        // * Fill the final attack message
        $message = $first_message.$mob_damage->msg;
        // var_dump($mob_damage["msg"]);
        $data = array(
            "message"=> $message,
            "current_mob_health" => $updated_stats['current_mob_health'],
            "current_player_health" => $updated_stats['current_player_health'],
            "current_player_mana" => $updated_stats['current_player_mana'],
            "updated_health_percentage" => $updated_stats['updated_health_percentage'],
            "kill_message" => $kill_message,
            "drops" => $drops,
            "status" => 200
        );

        echo json_encode($data);
        csrf(); // regenerate csrf token
    }
    public function applySkill($id){
        $hour = date('h:i A');
        $Battle = new Battle();
        $Monster = new Monster();
        $Monster->getMobStats($_REQUEST['hI'], $_REQUEST['hMI']);

        $Skills = new Skills();
        $User = new Usuario($_SESSION['user']['id']);
        // $User->getPlayerStats();
        $Skills->findSkill($id);
        $skill_level = $Skills->skill_level;
        $formula = $Skills->skill_formula;
        $variables = explode(',', trim($Skills->skill_formula_variables));

        foreach ($variables as $variable) {
            if($variable != 'skill_level'){
                $$variable = $User->$variable;
            }
            $formula = str_replace($variable, $$variable, $formula);
        }
        $result = eval("return $formula;");
        if($User->current_mana >= $Skills->cost){
            if($Skills->skill_type == 1){ // damage
                // * Evaluate the skill effect
                if($Skills->skill_effect == 2){ // Collateral damage
                    echo $Skills->applyCollateralDamageSkill(ceil($result));
                }elseif($Skills->skill_effect == 1){ // Regular damage
                    // * Player damage
                    $min_player_dmg = ceil($result - ($result * 0.02));
                    $max_player_dmg = ceil($result + ($result * 0.02));
            
                    $final_player_dmg = rand($min_player_dmg, $max_player_dmg);
                    $mob_defense = $Monster->defense;
                    
                    $playerDamage = ($final_player_dmg*(100/(90+$mob_defense)));
    
                    $lower_mob_health = ($Monster->current_mob_health)-ceil($playerDamage);
                    $Battle->player_attack($lower_mob_health,$_REQUEST['hMI']);
                    $first_message = "<li style='color:#42A100'>".$hour." - Has realizado: <span>".ceil($playerDamage)."</span> de daño con {$Skills->name}</li>";
                    $log_message1 = "Has realizado: ".ceil($playerDamage)." de daño con {$Skills->name}";
                    $color = "#42A100";
    
                    $User->take_mana($Skills->cost);
            
                    $updated_stats = $this->updateStats($_REQUEST['hMI']);
                    $mob_damage = json_decode($this->mobDamage($_REQUEST['hI'], $_REQUEST['hMI']));
    
                    // * Kill Mob and his consequences
                    $kill_message = "";
                    $drops = "";

                    BattleLog::log($log_message1, $color);
                    BattleLog::log($mob_damage->log_msg, $mob_damage->color);

                    if($updated_stats['current_mob_health'] <= 0){
                        $kill_message = $this->killMob($_REQUEST['hMI'],$_SESSION['user']['id']);
                        // * Drop items to player
                        $drops = $this->dropItems($Monster->id);
            
                        // * Fill zones if neccesary
                        $this->fillZone($Monster->zone);
                    }
    
                    // * Fill the final attack message
                    $message = $first_message.$mob_damage->msg;
                    $data = array(
                        "message"=> $message,
                        "current_mob_health" => $updated_stats['current_mob_health'],
                        "current_player_health" => $updated_stats['current_player_health'],
                        "current_player_mana" => $updated_stats['current_player_mana'],
                        "updated_health_percentage" => $updated_stats['updated_health_percentage'],
                        "kill_message" => $kill_message,
                        "drops" => $drops
                    );
            
                    echo json_encode($data);
                }
            }elseif($Skills->skill_type == 2){ // healing
                // * Determine if healing can be done
                $final_hp = $User->current_health+$result;
                if($final_hp > $User->max_health){
                    $hp = 0;
                    $missing_hp = $User->max_health-$User->current_health;
                    if($missing_hp > $result){
                        $hp = $result;
                    }else{
                        $hp = $missing_hp;
                    }
                    // * Apply healing
                    $User->heal($hp);
                    $first_message = "<li style='color:#42A100'>".$hour." - Te has curado: <span style='color:red;'>".ceil($hp)."</span> de vida con {$Skills->name}</li>";
                    $log_message1 = "Te has curado: ".ceil($hp)." de vida con {$Skills->name}";
                    $color = "#42A100";
                }else{
                    // * Apply healing
                    $User->heal(ceil($result));
                    $first_message = "<li style='color:#42A100'>".$hour." - Te has curado: <span style='color:red;'>".ceil($result)."</span> de vida con {$Skills->name}</li>";
                    $log_message1 = "Te has curado: ".ceil($result)." de vida con {$Skills->name}";
                    $color = "#42A100";
                }
                $User->take_mana($Skills->cost);
        
                $mob_damage = json_decode($this->mobDamage($_REQUEST['hI'], $_REQUEST['hMI']));
                $updated_stats = $this->updateStats($_REQUEST['hMI']);

                BattleLog::log($log_message1, $color);
                BattleLog::log($mob_damage->log_msg, $mob_damage->color);

                // * Fill the final attack message
                $message = $first_message.$mob_damage->msg;
                $data = array(
                    "message"=> $message,
                    "current_mob_health" => $updated_stats['current_mob_health'],
                    "current_player_health" => $updated_stats['current_player_health'],
                    "current_player_mana" => $updated_stats['current_player_mana'],
                    "updated_health_percentage" => $updated_stats['updated_health_percentage'],
                    "kill_message" => ""
                );
            
                echo json_encode($data);
            }
        }else{
            $missing_mana = $Skills->cost-$User->current_mana;
            $message = "<li style='color:#EC0000'>".$hour." - Necesitas: <span style= 'color:#005CE8;'>{$missing_mana}</span> para poder utilizar esta habilidad!</li>";
            $data = array(
                "message"=> $message,
                "kill_message" => "",
                "insufficient_mana" => 1
            );
            echo json_encode($data);
        }
    }
    public function checkMobStatus(){
        $Monster = new Monster();
        $Monster->getMobStats($_REQUEST['hMI'], $_REQUEST['hI']);

        echo $Monster->dead;
    }
    public function fillZone($id){
        $total_mobs = Zones::countZoneMobs($id);
        if($total_mobs < 60){
            while($total_mobs < 90){
                $available_monsters = Zones::selectAvailableMobs($id); //get mobs that can be inserted into the zone
                foreach($available_monsters as $av_monster){
                    $mon_id[] = $av_monster['id'];
                }
                $rnd_id = array_rand($mon_id); 
                $rnd_mon = $mon_id[$rnd_id];//random available monster id
                //get Mob level
                $get_mob_level = Zones::getMobLevel($rnd_mon);
                $decrease_special_mob_rnd = rand(0, 100);
                if($decrease_special_mob_rnd >= 30){
                    $get_mob_level2 = $get_mob_level['level']; //70% chance of getting initial monster level
                }elseif($decrease_special_mob_rnd <= 30){
                    $get_mob_level2 = $get_mob_level['level']+1; //30% chance of getting 1 level up monster
                }
                $rnd_mob_lvl = rand($get_mob_level['level'], $get_mob_level2);
                $data = array(
                    "monster" => $rnd_mon,
                    "level" => $rnd_mob_lvl,
                    "zone" => $id,
                    "health" => $rnd_mob_lvl*100,
                ); 
                $fill = Zones::fillZone($data);
                $total_mobs = $total_mobs+1;
                unset($mon_id);
            }
        }
        return 0;
    }
    public function dropItems($id){
        
        $hour = date('h:i A');
        $Usuario = new Usuario($_SESSION['user']['id']);
        
        $player_luck = $Usuario->luck;
        $MobStats = MobList::specificMonster($id);
        
        $noDrops = "<span style= 'color: #267C00;'>".$hour." - ¡No has conseguido ningún objeto!</span>";

        $Item = new ItemController();
        $drop = $Item->drop(($MobStats->drops), $player_luck); //Drop method

        if($drop == ''){
            return $noDrops;
        }else{
            return $drop;
        }

    }
    public function killMob(){
        $hour = date('h:i A');
        $Monster = new Monster();
        $Monster->getMobStats($_REQUEST['hI'], $_REQUEST['hMI']);
        $User = new Usuario($_SESSION['user']['id']);
        // $User->getPlayerStats();

        $Monster->kill_mob($_REQUEST['hMI'],$_SESSION['user']['id']);

        // * Give XP to player
        $xp_given = ($Monster->level)*100;
        $player_xp = $User->xp;
        $User->give_xp($xp_given,$User->id);

        // * Give gold to player
        $gold = ceil($Monster->level * 2.25);
        $User->give_gold($gold);

        // * Determine if player levels up
        $player_max_xp = $User->maxXp;
        $new_player_xp = $this->updatePlayerStats();
        if($new_player_xp['current_xp'] >= $player_max_xp){ //if player levels up
            UsuarioController::levelUp();
        }

        $kill_message = "<span style='color: white;'>".$hour." - Has ganado: <span style='color:gold;'> ".$gold." </span> de oro y <span style='color:purple;'>".$xp_given."</span> de experiencia!</span>";
        BattleLog::log("Has ganado: {$gold} de oro y {$xp_given} de experiencia! ", "gold");
        return $kill_message;
    }
    public function mobDamage($id,$mId){
        $hour = date('h:i A');
        $Battle = new Battle();
        $Monster = new Monster();
        $Monster->getMobStats($id, $mId);
        $User = new Usuario($_SESSION['user']['id']);
        $color = "#ccc";

        //* Mob damage
        $mob_damage = $Monster->damage;

        //* Mob's dmg variation
        $min_mob_dmg = ceil($mob_damage - ($mob_damage * 0.02));
        $max_mob_dmg = ceil($mob_damage + ($mob_damage * 0.02));

        $final_mob_dmg = rand($min_mob_dmg, $max_mob_dmg);
        $mobDamage = ($final_mob_dmg*(100/(100+$User->defense)));

        // * Lower both mob and player health
        $lower_player_health = ($User->current_health)-ceil($mobDamage);// * Calculate mob agility
        if($Monster->id_element == 4){ //if mob has air element has more agility
            $mob_agility = ceil($Monster->level * 2);
        }elseif($Monster->id_element == 3){//if mob has earth element has less agility
            $mob_agility = ceil($Monster->level);
        }else{
            $mob_agility = ceil($Monster->level * 1.5);
        }
        $mob_aim = ($mob_agility/2);
        $mob_name = $Monster->name;// * Calculate mob's hit chances
        $mob_hit_chance = (($mob_aim*0.85)-($User->dodge*0.15))*100;
        if($mob_hit_chance <= 0){ //if the mob's hit chance is lower than 0
            $mob_hit_chance = 5;
        }
        if($mob_hit_chance >= 100){ //if the mob's hit chance is higher than 100
            $mob_hit_chance = 95;
        }
        $rand_factor_hit_chance2 = rand(0,100);
        if($rand_factor_hit_chance2 < $mob_hit_chance){
            // * Execute method for a successful mob attack
            $Battle->mob_basic_attack($lower_player_health,$_SESSION['user']['id']);
            $second_message = "<li style='color:#ccc;'>".$hour." - ".$mob_name." te ha realizado: <span style='color:".$Battle->element_color."';>".ceil($mobDamage)."</span> usando ataque básico.</li>";
            $log_message2 = $mob_name." te ha realizado: ".ceil($mobDamage)." usando ataque básico";
            $color = "#ccc";
        }else{
            $second_message = "<li>".$hour." - ".$mob_name." ha intentado golpearte, pero lo has esquivado</li>";
            $log_message2 = $mob_name." ha intentado golpearte, pero lo has esquivado";
            $color = "#808080";
        }
        $data = array(
            "msg" => $second_message,
            "log_msg" => $log_message2,
            "color" => $color
        );
        return json_encode($data);
    }
}