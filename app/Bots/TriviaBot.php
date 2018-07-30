<?php
/**
 * NOTICE OF LICENSE
 *
 * UNIT3D is open-sourced software licensed under the GNU General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 * @author     HDVinnie
 */

namespace App\Bots;

use App\Game;
use App\Player;
use App\Question;
use App\QuestionSet;

class TriviaBot
{
    private $channel;
    private $bot_id;


    public function __construct($bot_id)
    {
        $this->channel = "Trivia";
        $this->bot_id = $bot_id;
    }

    public function start()
    {
        $game = Game::first();
        if (empty($game)) {
            $game = Game::create(["started" => 0, "stopping" => 0, "delay" => 20]);
        }

        //set all questions to OFF
        $on = $this->getCurrentQuestion();
        while (!empty($on)) {
            $on->current_hint = 0;
            $on->save();
            $on = $this->getCurrentQuestion();
        }

        // \Question::update_all(array('set' => 'current_hint = 0'));
        //set a random question to ON
        $question = Question::find('first', ["order" => "RAND()"]);
        $question->current_hint = 1;
        $question->save();

        $game->started = 1;
        $game->save();
    }

    public function stop()
    {
        //set the flag in the database to say the game is not running
        $game = Game::first();
        $game->stopping = 1;
        $game->save();
    }

    public function getCurrentQuestion()
    {
        return Question::find('first', ['conditions' => 'current_hint > 0']);
    }


    /**
     * @param bool $question_file
     * @param bool $force
     * @return string
     */
    public function load($question_file, $force = false)
    {
        $response = "";
        if (!$this->is_loaded($question_file) || $force) {
            //add questions from the given file to the database
            $file = __DIR__ . '/questions/' . $question_file;
            if (file_exists($file)) {
                $questions = file($file, FILE_IGNORE_NEW_LINES);

                $title = ltrim($questions[0], "# ");
                $question_set = Question_set::create(["filename" => $question_file, "title" => $title]);

                foreach ($questions as $question) {
                    $question = trim($question);
                    //ignore comment lines in the file
                    if ($question[0] != "#") {
                        //split into token parts
                        $split = explode('|', $question);
                        //first item is the question
                        $q = trim(array_shift($split));
                        if (!empty($q) && !empty($split)) {
                            $a = serialize($split);
                            try {
                                //duplicate questions won't be saved because of the unique property on the db column
                                Question::create([
                                    'question_set' => $question_set->id,
                                    'question' => $q,
                                    'answer' => $a
                                ]);
                            } catch (\Exception $e) {
                                //just skip this if it didn't add, might be a duplicate question field.
                            }
                        }
                    }
                }
                $total_questions = $this->get_total_questions();
                $response .= "Questions from *{$title}* loaded! There are *{$total_questions}* in the database.";
            } else {
                $response .= "No question file found";
            }
        } else {
            $set = $this->get_question_set_by_filename($question_file);
            $response .= "The *{$set->title}* set is already loaded!";
        }
        return $response;
    }

    /**
     * @return mixed
     */
    public function get_total_questions()
    {
        return Question::count();
    }

    /**
     * @param $question
     * @return bool
     */
    private function check_question_exists($question)
    {
        $q = Question::find_by_question($question);
        return (!empty($q));
    }

    /**
     * @param $filename
     * @return mixed
     */
    private function get_question_set_by_filename($filename)
    {
        $set = Question_set::find_by_filename($filename);
        return $set;
    }

    /**
     * @param $set_name
     */
    public function unload($set_name)
    {
        //remove questions from the given set name from the database
    }


    /**
     * @param $question_file
     * @return bool
     */
    private function is_loaded($question_file)
    {
        $set = Question_set::find_by_filename($question_file);
        return (!empty($set));
    }

    /**
     * @param string $message
     *
     */
    public function sendMessageToChannel($message)
    {
        $this->message->create([
            'user_id' => $this->getBotID(),
            'chatroom_id' => $this->getChannel(),
            'message' => $message
        ]);
    }

    /**
     * @return mixed
     */
    public function getBotID()
    {
        return $this->bot_id;
    }

    /**
     * @param mixed $bot_id
     */
    public function setBotName($bot_id)
    {
        $this->bot_id = $bot_id;
    }

    /**
     * @return mixed
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    public function started()
    {
        $game = Game::first();
        return ($game->started == 1);
    }
}