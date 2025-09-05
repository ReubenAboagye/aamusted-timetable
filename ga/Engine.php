<?php
/**
 * Simple GA engine for timetable generation.
 * This is a minimal, synchronous implementation intended as a starting point.
 */
require_once __DIR__ . '/DBLoader.php';
require_once __DIR__ . '/Representation.php';
require_once __DIR__ . '/Fitness.php';

class Engine {
    private $conn;
    private $data;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
        $loader = new DBLoader($conn);
        $this->data = $loader->loadAll();
    }

    public function run(array $opts = []) {
        $populationSize = $opts['population'] ?? 200;
        $generations = $opts['generations'] ?? 500;

        $classCourses = $this->data['class_courses'];
        $days = $this->data['days'];
        $timeSlots = $this->data['time_slots'];
        $rooms = $this->data['rooms'];

        // maps
        $classMap = [];
        foreach ($this->data['classes'] as $c) $classMap[$c['id']] = $c;
        $lectMap = [];
        foreach ($this->data['lecturer_courses'] as $lc) $lectMap[$lc['id']] = $lc;

        $fitnessCalc = new Fitness($rooms);

        // initialize population
        $population = [];
        $templateGenome = Representation::emptyGenome($classCourses);
        for ($i = 0; $i < $populationSize; $i++) {
            $genome = [];
            foreach ($templateGenome as $gid => $gtemp) {
                $genome[$gid] = Representation::randomGene($gtemp, $days, $timeSlots, $rooms);
            }
            $population[] = $genome;
        }

        $best = null;
        for ($gen = 0; $gen < $generations; $gen++) {
            // evaluate
            $evaluated = [];
            foreach ($population as $ind) {
                $f = $fitnessCalc->evaluate($ind, $classMap, $lectMap);
                $evaluated[] = ['genome' => $ind, 'fitness' => $f];
            }

            usort($evaluated, function($a,$b){ return $a['fitness']['score'] <=> $b['fitness']['score']; });
            if ($best === null || $evaluated[0]['fitness']['score'] < $best['fitness']['score']) $best = $evaluated[0];

            // early exit if feasible
            if ($best['fitness']['hard'] == 0) break;

            // selection + simple crossover+mutation -> next generation
            $next = [];
            // elitism
            $elitism = max(1, (int)round($populationSize * 0.02));
            for ($e = 0; $e < $elitism; $e++) $next[] = $evaluated[$e]['genome'];

            while (count($next) < $populationSize) {
                // tournament selection
                $p1 = $evaluated[array_rand($evaluated)]['genome'];
                $p2 = $evaluated[array_rand($evaluated)]['genome'];
                // crossover: uniform swap of 5% genes
                $child = $p1;
                $genes = array_keys($child);
                $swapCount = max(1, (int)round(count($genes) * 0.05));
                for ($s = 0; $s < $swapCount; $s++) {
                    $g = $genes[array_rand($genes)];
                    $child[$g] = $p2[$g];
                }
                // mutation: random reassign 1% genes
                $mutCount = max(1, (int)round(count($genes) * 0.01));
                for ($m = 0; $m < $mutCount; $m++) {
                    $g = $genes[array_rand($genes)];
                    $child[$g] = Representation::randomGene($child[$g], $days, $timeSlots, $rooms);
                }
                $next[] = $child;
            }

            $population = $next;
        }

        return $best;
    }
}

?>


