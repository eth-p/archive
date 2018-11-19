#!/usr/bin/env php
<?php

class stdout {

	static private $buffer = '';

	static function write($str) {
		static::$buffer .= $str;
	}

	static function flush() {
		$passes = ceil(strlen(static::$buffer) / 1024);
		for ($i = 0; $i < $passes; $i++) {
			echo substr(static::$buffer, $i * 1024, 1024);
		}

		static::$buffer = '';
	}

}

/**
 * A class for interacting with the terminal.
 */
class Terminal {

	/**
	 * Prepare the terminal.
	 */
	static public function prepare() {
		`stty raw`;
		`stty -echo`;
		`stty -icanon min 0 time 0`;
		stream_set_blocking(STDIN, false);
		stdout::write("\033[?25l");
	}

	/**
	 * Clean up the terminal.
	 */
	static public function cleanup() {
		stdout::write("\033[?25h");
		stream_set_blocking(STDIN, true);
		`stty echo`;
		`stty cooked`;
	}

	/**
	 * Get the terminal size.
	 * @return int[]
	 */
	static public function size() {
		$size = explode(' ', `stty size`);
		return [intval($size[1]), intval($size[0])];
	}

	/**
	 * Convert a character ordinal or ordinal array into a named key.
	 *
	 * @param array|string $seq The characters.
	 * @param boolean $map_shift If true, maps shift modifiers. Uses original characters if false.
	 * @return string
	 */
	static protected function getKeyName($seq, $map_shift) {
		if (is_array($seq)) {
			switch ($seq) {
				case [27]:						return "<escape>";
				case [27, 91, 65]:				return "<up>";
				case [27, 91, 66]:				return "<down>";
				case [27, 91, 67]:				return "<right>";
				case [27, 91, 68]:				return "<left>";
				case [27, 91, 51, 126]:			return "<delete>";
				case [27, 79, 80]:				return "<f1>";
				case [27, 79, 81]:				return "<f2>";
				case [27, 79, 82]:				return "<f3>";
				case [27, 79, 83]:				return "<f4>";
				case [27, 91, 49, 53, 126]:		return "<f5>";
				case [27, 91, 49, 55, 126]:		return "<f6>";
				case [27, 91, 49, 56, 126]:		return "<f7>";
				case [27, 91, 49, 57, 126]:		return "<f8>";
				case [27, 91, 50, 48, 126]:		return "<f9>";
				case [27, 91, 50, 49, 126]:		return "<f10>";
				// TODO F11
				case [27, 91, 50, 52, 126]:		return "<f12>";
				case [27, 91, 50, 53, 126]:		return "<f13>";
				// Other F keys don't report?
				case [27, 91, 50, 57, 126]:		return "<f16>";
				case [27, 91, 51, 59, 50, 126]: return "S-<delete>";
				case [27, 91, 72]:				return "S-<home>";
				case [27, 91, 70]:				return "S-<end>";
				case [27, 91, 54, 126]:			return "S-<pgdn>";
				case [27, 91, 53, 126]:			return "S-<pgup>";

			}
		}

		switch ($seq) {

			case 127:   return "<backspace>";
			case 10:	return "<enter>";
			case 13:	return "<return>";
			case 32:	return "<space>";

			// Regular character.
			default:

				// 1-26 = CTRL+<n>
				if ($seq > 0 && $seq <= 26) {
					return "C-" . chr(96 + $seq);
				}

				// Shift: 0-9, A-Z
				if ($map_shift) {
					if ($seq > 64 && $seq <= 90) {
						return "S-".strtolower(chr($seq));
					}

					if ($seq >= 33 && $seq <= 45 || $seq === 64 || $seq === 94) {
						switch (chr($seq)) {
							case "!":  return "S-1";
							case "@":  return "S-2";
							case "#":  return "S-3";
							case "$":  return "S-4";
							case "%":  return "S-5";
							case "^":  return "S-6";
							case "&":  return "S-7";
							case "*":  return "S-8";
							case "(":  return "S-9";
							case ")": return "S-0";
						}
					}
				}

				// Other.
				return chr($seq);

		}
	}

	/**
	 * Read a character or ANSI escape sequence and return its common name.
	 *
	 * For special characters: <return>
	 * For shift modifiers:   S-[char]
	 * For control modifiers: C-[char]
	 * For ANSI sequences:	[27,...,...]
	 *
	 * @param boolean $map_shift If true, maps shift modifiers. Uses original characters if false.
	 *
	 * @return array|string
	 */
	static public function read($map_shift=true) {
		while (true) {
			$char = fgetc(STDIN);
			if ($char === false) {
				usleep(100);
				continue;
			}

			// Escape sequence.
			$charo = ord($char);
			if ($charo === 27) {
				$buffer = [27];
				for ($i = 0; $i < 10; $i++) {
					$char = fgetc(STDIN);
					if ($char !== false)
						array_push($buffer, ord($char));

					usleep(100);
				}

				return static::getKeyName($buffer, $map_shift);
			} else {
				return static::getKeyName($charo, $map_shift);
			}
		}
	}


	/**
	 * Asynchronously read a character or ANSI escape sequence and return its common name.
	 * If there is nothing in STDIN, this will return null.
	 *
	 * For special characters: <return>
	 * For shift modifiers:   S-[char]
	 * For control modifiers: C-[char]
	 * For ANSI sequences:	[27,...,...]
	 *
	 * @param boolean $map_shift If true, maps shift modifiers. Uses original characters if false.
	 *
	 * @return array|string|null
	 */
	static public function readAsync($map_shift=true) {
		while (true) {
			$char = fgetc(STDIN);
			if ($char === false) {
				return null;
			}

			// Escape sequence.
			$charo = ord($char);
			if ($charo === 27) {
				$buffer = [27];
				for ($i = 0; $i < 10; $i++) {
					$char = fgetc(STDIN);
					if ($char !== false)
						array_push($buffer, ord($char));

					usleep(100);
				}

				return static::getKeyName($buffer, $map_shift);
			} else {
				return static::getKeyName($charo, $map_shift);
			}
		}
	}
}

/**
 * A static class that holds variables used to tune difficulty and colors.
 */
class Tuning {

	const GAME_BG = 16;
	const SIDEBAR_FG = 255;
	const SIDEBAR_BG = 241;
	const STAT_FG = 231;
	const STAT_BG = 16;

	/**
	 * A tuning array. This determines the ANSI-256 color of each block.
	 * Available block IDs are as follows:
	 *   0:  T
	 *   1:  L
	 *   2:  Backwards L
	 *   3:  Z
	 *   4:  Backwards Z
	 *   5:  Square
	 *   6:  I
	 *
	 * @var int[][]
	 */
	static $COLOR_BY_LEVEL = [
		[57, 172, 21, 196, 40, 226, 39],
		[57, 124, 208, 220, 34, 39, 25],
		[147, 183, 105, 171, 135, 169, 201],
		[17, 52, 94, 63, 29, 172, 56],
		[161, 196, 166, 208, 204, 168, 129],
		[232, 237, 240, 244, 247, 250, 253],
		[16, 16, 16, 16, 16, 16, 16],
		[16, 16, 16, 16, 16, 16, 16],
		[16, 16, 16, 16, 16, 16, 16],
		[16, 16, 16, 16, 16, 16, 16],
	];

	static $SPEED_BY_LEVEL = [
		3000,
		2900,
		2700,
		2400,
		2000,
		1500,
		1000,
		800,
		600,
		500
	];

	static $FUSE_BY_LEVEL = [
		10,
		9,
		8,
		7,
		6,
		6,
		5,
		5,
		4,
		3
	];


	/**
	 * A tuning array. This determines the contents of the shuffled block array.
	 * Available block IDs are as follows:
	 *   0:  T
	 *   1:  L
	 *   2:  Backwards L
	 *   3:  Z
	 *   4:  Backwards Z
	 *   5:  Square
	 *   6:  I
	 *
	 * @var int[][]
	 */
	static $BLOCKS_BY_LEVEL = [
		[0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6],
		[0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6],
		[0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6],
		[0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 4, 5, 5, 5, 6, 6, 6],
		[0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 4, 5, 5, 5, 6, 6, 6],
		[0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 4, 5, 5, 5, 6, 6, 6],
		[0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 6, 6, 6, 6],
		[0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 6, 6, 6, 6],
		[0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 6, 6, 6, 6],
		[0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 4, 4, 4, 4, 4, 5, 5, 5, 5, 5, 6, 6, 6, 6, 6],
	];

	static $LEVEL_BY_LINES = [
		0,
		5,
		10,
		15,
		20,
		30,
		40,
		50,
		65,
		80
	];
}

/**
 * Class Termtris
 */
class Termtris {

	const CHAR_TETROMINO 	= "[]";
	const CHAR_EMPTY     	= "  ";
	const CHAR_EMPTY_GUIDE	= "\033[0;38;5;240m||\033[0m";
	const UI_TBOX_WIDTH  	= 10;
	const UI_TBOX_HEIGHT 	= 6;

	const TETROMINO_T = 0;
	const TETROMINO_L = 1;
	const TETROMINO_BACKWARDS_L = 2;
	const TETROMINO_Z = 3;
	const TETROMINO_BACKWARDS_Z = 4;
	const TETROMINO_SQUARE = 5;
	const TETROMINO_I = 6;

	protected $running;
	protected $dropping;

	protected $paused;

	/**
	 * The block ID -> color mapper.
	 * @var int[]
	 */
	protected $colors;

	/**
	 * The current level.
	 * @var int
	 */
	protected $level;

	protected $lines;

	protected $score;

	protected $tsize;
	protected $deck;

	protected $tet_x;
	protected $tet_y;
	protected $tet_grid;
	protected $tet_grid_held;
	protected $tet_grid_next;
	protected $hold_cooldown;

	/**
	 * The terminal grid.
	 * @var array[]
	 */
	protected $grid;

	protected $gsize;

	/**
	 * The number of ticks until the game advances a frame.
	 * @var int
	 */
	protected $update_logic;

	protected $update_logic_max;

	/**
	 * The number of ticks until the game checks the terminal size.
	 * @var int
	 */
	protected $update_tsize;

	protected $update_fuse;
	protected $update_fuse_max;

	protected $render_cleared;
	protected $render_held;
	protected $render_lines;
	protected $render_level;
	protected $render_score;
	protected $render_next;
	protected $render_grid;

	protected $use_guides;

	public function init() {
		$this->running = true;
		$this->tsize = Terminal::size();
		$this->update_tsize = 10000;

		// Make new lines.
		stdout::write("\033[1;1H");
		for ($i = 0; $i < $this->tsize[1]; $i++) {
			stdout::write("\n");
		}
	}

	public function reset() {
		$this->level = 0;
		$this->score = 0;
		$this->lines = 0;
		$this->deck = Tuning::$BLOCKS_BY_LEVEL[$this->level];
		$this->colors = Tuning::$COLOR_BY_LEVEL[$this->level];
		$this->update_logic_max = Tuning::$SPEED_BY_LEVEL[$this->level];
		$this->update_logic = 0;
		$this->update_fuse_max = Tuning::$FUSE_BY_LEVEL[$this->level];
		$this->update_fuse = null;
		$this->hold_cooldown = false;
		$this->tet_grid_held = null;

		shuffle($this->deck);

		// Make grid.
		$this->gsize = [16, $this->tsize[1]];
		$this->grid = [];
		for ($i = 0; $i < $this->gsize[1]; $i++) {
			array_push($this->grid, array_fill(0, $this->gsize[0], null));
		}

		// Select blocks.
		$this->blockNext();
		$this->blockNext();

		// Make new lines.
		$this->renderClear();
		$this->renderFlush();
	}

	protected function blockNext() {
		// Shift old next to falling.
		$this->tet_grid = $this->tet_grid_next;

		// Pull from deck.
		$selected = array_shift($this->deck);

		// Refill deck.
		if (count($this->deck) < 1) {
			$this->deck = Tuning::$BLOCKS_BY_LEVEL[$this->level];
			shuffle($this->deck);
		}

		// Generate tetromino.
		switch ($selected) {
			case Termtris::TETROMINO_L:
				$tid = Termtris::TETROMINO_L;
				$this->tet_grid_next = [
					[$tid, null],
					[$tid, null],
					[$tid, $tid]
				];
				break;

			case Termtris::TETROMINO_BACKWARDS_L:
				$tid = Termtris::TETROMINO_BACKWARDS_L;
				$this->tet_grid_next = [
					[null, $tid],
					[null, $tid],
					[$tid, $tid]
				];
				break;

			case Termtris::TETROMINO_T:
				$tid = Termtris::TETROMINO_T;
				$this->tet_grid_next = [
					[$tid, $tid, $tid],
					[null, $tid, null]
				];
				break;

			case Termtris::TETROMINO_SQUARE:
				$tid = Termtris::TETROMINO_SQUARE;
				$this->tet_grid_next = [
					[$tid, $tid],
					[$tid, $tid]
				];
				break;

			case Termtris::TETROMINO_I:
				$tid = Termtris::TETROMINO_I;
				$this->tet_grid_next = [
					[$tid],
					[$tid],
					[$tid],
					[$tid]
				];
				break;

			case Termtris::TETROMINO_Z:
				$tid = Termtris::TETROMINO_Z;
				$this->tet_grid_next = [
					[$tid, $tid, null],
					[null, $tid, $tid],
				];
				break;

			case Termtris::TETROMINO_BACKWARDS_Z:
				$tid = Termtris::TETROMINO_BACKWARDS_Z;
				$this->tet_grid_next = [
					[null, $tid, $tid],
					[$tid, $tid, null],
				];
				break;
		}

		// Reset position.
		$this->tet_x = floor($this->gsize[0] / 2 - count($this->tet_grid_next[0]) / 2);
		$this->tet_y = -count($this->tet_grid_next);

		// Render.
		$this->render_next = true;
	}

	public function main() {
		while ($this->running) {
			// Check for terminal size update.
			if (--$this->update_tsize < 1) {
				$this->update_tsize = 1000;
				$tsize = Terminal::size();
				if ($this->tsize != $tsize) {
					$this->tsize = $tsize;
					$this->reset();
				}
			}

			// Handle user input.
			$this->handleInput();

			// Handle game updates.
			if (!$this->paused && --$this->update_logic < 1) {
				$this->update_logic = $this->update_logic_max;
				$this->renderFalling(true);
				$this->handleGame();
				$this->renderFalling(false);
			}

			// Handle rendering.
			$this->handleRender();
			$this->renderFlush();

			// Sleep.
			usleep(100);
		}

		$this->renderClear();
		$this->renderFlush();
		stdout::write("\033[1;1H");
	}

	protected function handleInput() {
		switch (Terminal::readAsync()) {
			case "<up>":
				$this->blockDrop();
				break;

			case "<down>":
				$this->update_fuse = 0;
				$this->update_logic = 0;
				break;

			case "<left>":
				if ($this->blockLeft() && $this->update_fuse > 0) $this->update_fuse+=1;
				break;

			case "<right>":
				if ($this->blockRight() && $this->update_fuse > 0) $this->update_fuse+=1;
				break;

			case ",":
			case "<":
				if ($this->blockCCW() && $this->update_fuse > 0) $this->update_fuse+=2;
				break;

			case ".":
			case ">":
				if ($this->blockCW() && $this->update_fuse > 0) $this->update_fuse+=2;
				break;

			case "<space>":
			case "<enter>":
			case "<return>":
				if (!$this->hold_cooldown) {
					$this->hold_cooldown = true;
					$this->blockHold();
				}
				break;

			case "C-c":
				$this->running = false;
				break;

			case "g":
				$this->use_guides = !$this->use_guides;
				$this->render_grid = true;
				break;

			case "p":
				$this->paused = !$this->paused;
				break;

			case "S-1":
				Terminal::cleanup();
				echo "\nDEBUG:\n";
				for ($i = 0; $i < count($this->grid); $i++) {
					for ($j = 0; $j < count($this->grid[$i]); $j++) {
						if ($this->grid[$i][$j] === null) {
							echo "-";
						} else {
							echo $this->grid[$i][$j];
						}
					}
					echo "\n";
				}
				exit;
				break;

			case "=":
			case "+":
				if ($this->level + 1 > count(Tuning::$LEVEL_BY_LINES) - 1) break;
				$this->render_level = true;
				$this->render_grid = true;
				$this->render_next = true;
				$this->render_held = true;
				$this->level++;

				$this->colors = Tuning::$COLOR_BY_LEVEL[$this->level];
				$this->update_fuse_max = Tuning::$FUSE_BY_LEVEL[$this->level];
				$this->update_logic_max = Tuning::$SPEED_BY_LEVEL[$this->level];
				break;

			case "-":
			case "_":
			if ($this->level < 1) break;
				$this->render_level = true;
				$this->render_grid = true;
				$this->render_next = true;
				$this->render_held = true;
				$this->level--;

				$this->colors = Tuning::$COLOR_BY_LEVEL[$this->level];
				$this->update_fuse_max = Tuning::$FUSE_BY_LEVEL[$this->level];
				$this->update_logic_max = Tuning::$SPEED_BY_LEVEL[$this->level];
				break;
		}
	}

	protected function blockDrop() {
		$this->renderFalling(true);
		$this->dropping = true;
		while ($this->dropping) {
			$this->handleGame();
		}
	}

	protected function blockCCW() {
		$this->renderFalling(true);
		$twidth = count($this->tet_grid[0]);
		$theight = count($this->tet_grid);
		$ng = array_fill(0, $twidth, array_fill(0, $theight, null));
		for ($i = 0; $i < $theight; $i++) {
			for ($j = 0; $j < $twidth; $j++) {
				$ng[$twidth - $j - 1][$i] = $this->tet_grid[$i][$j];
			}
		}

		$y = $this->tet_y;
		$x = $this->tet_x;
		while ($x + $theight > $this->gsize[0]) {
			$x--;
		}

		while ($y + $twidth > $this->gsize[1]) {
			$y--;
		}

		if ($this->hasCollision($ng, $x, $y)) return false;

		$this->renderFalling(true);
		$this->tet_x = $x;
		$this->tet_y = $y;
		$this->tet_grid = $ng;
		$this->renderFalling(false);
		return true;
	}

	protected function blockCW() {
		$twidth = count($this->tet_grid[0]);
		$theight = count($this->tet_grid);
		$ng = array_fill(0, $twidth, array_fill(0, $theight, null));
		for ($i = 0; $i < $theight; $i++) {
			for ($j = 0; $j < $twidth; $j++) {
				$ng[$j][$i] = $this->tet_grid[$theight - $i - 1][$j];
			}
		}

		$y = $this->tet_y;
		$x = $this->tet_x;
		while ($x + $theight > $this->gsize[0]) {
			$x--;
		}

		while ($y + $twidth > $this->gsize[1]) {
			$y--;
		}

		if ($this->hasCollision($ng, $x, $y)) return false;

		$this->renderFalling(true);
		$this->tet_x = $x;
		$this->tet_y = $y;
		$this->tet_grid = $ng;
		$this->renderFalling(false);
		return true;
	}

	protected function blockHold() {
		if ($this->tet_grid_held === null) {
			// Clear the falling one.
			$this->renderFalling(true);

			// Allow the next to be re-rendered.
			$this->render_next = true;

			// Move the falling one to the held position, create a new falling one, and then create a new next one.
			$this->tet_grid_held = $this->tet_grid;
			$this->blockNext();
		} else {
			// Clear the falling one.
			$this->renderFalling(true);

			// Swap.
			$temp = $this->tet_grid;
			$this->tet_grid = $this->tet_grid_held;
			$this->tet_grid_held = $temp;

			// Reset position.
			$this->tet_x = floor($this->gsize[0] / 2 - count($this->tet_grid_next[0]) / 2);
			$this->tet_y = -count($this->tet_grid_next);
		}

		$this->render_held = true;
	}

	protected function blockLeft() {
		if ($this->tet_x - 1 < 0) return false;
		if ($this->hasCollision($this->tet_grid, $this->tet_x - 1, $this->tet_y)) return false;

		$this->renderFalling(true);
		$this->tet_x--;
		$this->renderFalling();
		return true;
	}

	protected function blockRight() {
		if ($this->tet_x + count($this->tet_grid[0]) + 1 > $this->gsize[0]) return false;
		if ($this->hasCollision($this->tet_grid, $this->tet_x + 1, $this->tet_y)) return false;

		$this->renderFalling(true);
		$this->tet_x++;
		$this->renderFalling();
		return true;
	}

	protected function handleGame() {
		// Check if it should fuse.
		$new_y = $this->tet_y + 1;
		if ($this->hasCollision($this->tet_grid, $this->tet_x, $new_y)) {
			if ($this->update_fuse === null) {
				$this->update_fuse = $this->update_fuse_max;
			} else if (--$this->update_fuse < 1) {
				$this->hold_cooldown = false;
				$this->renderFalling();
				$this->fuse();
				$this->checkLines();
				$this->blockNext();
			}

			return;
		}

		$this->update_fuse = null;
		$this->score += floor(1 * (1 + $this->level / 5) * ($this->dropping ? 3 : 1));
		$this->tet_y++;

		$this->render_score = true;
	}

	protected function hasCollision($tet, $x, $y) {
		if ($y + count($tet) > count($this->grid)) return true;

		for ($i = 0; $i < count($tet); $i++) {
			$row = $tet[$i];
			for ($j = 0; $j < count($row); $j++) {
				if ($y + $i < 0) continue;

				$id = $row[$j];
				if ($id !== null && $this->grid[$y + $i][$x + $j] !== null) {
					return true;
				}
			}
		}

		return false;
	}

	protected function checkLines() {
		$cleared = 0;
		for ($i = 0; $i < $this->gsize[1]; $i++) {
			foreach ($this->grid[$i] as $col) {
				if ($col === null) continue 2;
			}

			// Yay!
			array_splice($this->grid, $i, 1, []);
			array_unshift($this->grid, array_fill(0, $this->gsize[0], null));
			$cleared++;
			$i--;
		}

		if ($cleared > 0) {
			$this->lines += $cleared;
			$this->score += floor(1000 * (1 + $this->level / 5) * (1 + ($cleared-1) / 4));
			$this->checkLevel();

			$this->render_grid = true;
			$this->render_score = true;
			$this->render_lines = true;
		}
	}

	protected function checkLevel() {
		$max = count(Tuning::$LEVEL_BY_LINES) - 1;
		for ($i = $max; $i >= 0; $i--) {
			if ($this->lines >= Tuning::$LEVEL_BY_LINES[$i]) {
				if ($i > $this->level) {
					$this->render_level = true;
					$this->render_grid = true;
					$this->render_next = true;
					$this->render_held = true;
					$this->level = $i;

					$this->colors = Tuning::$COLOR_BY_LEVEL[$i];
					$this->update_fuse_max = Tuning::$FUSE_BY_LEVEL[$i];
					$this->update_logic_max = Tuning::$SPEED_BY_LEVEL[$i];
				}

				return;
			}
		}
	}

	protected function fuse() {
		$this->dropping = false;
		$tx = $this->tet_x;
		$ty = $this->tet_y;
		for ($i = 0; $i < count($this->tet_grid); $i++) {
			$row = $this->tet_grid[$i];
			for ($j = 0; $j < count($row); $j++) {
				$id = $row[$j];
				if ($id !== null) {
					if ($ty + $i < 0) {
						$this->gameOver();
						return;
					}

					$this->grid[$ty + $i][$tx + $j] = $id;
					$this->score += floor(10 * (1 + $this->level / 5));
				}
			}
		}

		$this->render_score;
	}

	protected function gameOver() {
		$this->reset();
	}

	protected function handleRender() {
		$this->renderHUD($this->render_cleared);
		$this->renderGrid($this->render_cleared);

		if ($this->render_cleared) {
			$this->render_cleared = false;
		}
	}

	protected function renderFalling($clear=false) {
		$lbound = floor($this->tsize[0] / 2) - floor($this->gsize[0]);
		if ($clear) {
			stdout::write("\033[48;5;" . Tuning::GAME_BG . "m");														// BG_256	{Tuning::GAME_BG}
		}

		$lid = null;
		$x = $lbound + ($this->tet_x * 2);
		$y = $this->tet_y;
		$height = count($this->tet_grid);
		stdout::write("\033[".max(1, $this->tet_y+1).";1H");															// CUP		{$this->tet_y+1},1
		for ($i = 0; $i < $height; $i++) {
			if (++$y <= 0) {
				continue;
			}

			$row = $this->tet_grid[$i];
			stdout::write("\033[".$x."G");																				// CHA		{$x}
			$ccon = 1;
			foreach ($row as $col) {
				if ($this->use_guides) {
					$ccon++;
				}

				if ($col === null) {
					stdout::write("\033[2C");																			// CUF		2
				} else {
					if ($clear) {
						if ($this->use_guides) {
							if (($this->tet_x + $ccon) % 2 === 0) {
								stdout::write(static::CHAR_EMPTY);
							} else {
								stdout::write(static::CHAR_EMPTY_GUIDE);
							}
						} else {
							stdout::write(static::CHAR_EMPTY);
						}
					} else {
						if ($lid !== $col) {
							$lid = $col;
							stdout::write("\033[48;5;" . $this->colors[$col] . "m");                                    // BG_256	{$this->colors[CELL_ID]}
						}

						stdout::write(static::CHAR_TETROMINO);
					}
				}
			}
			stdout::write("\033[B");																					// CUD		1
		}

	}

	protected function renderGrid($full=false) {
		if ($full || $this->render_grid) {
			$this->render_grid = false;
			$gwidth = $this->gsize[0];
			$gheight = $this->gsize[1];
			$lbound = floor($this->tsize[0] / 2) - floor($gwidth);

			// Clear field.
			stdout::write("\033[1;1H");																					// CUP		1,1
			stdout::write("\033[48;5;" . Tuning::GAME_BG . "m");														// BG_256	{Tuning::GAME_BG}
			for ($i = 0; $i < $gheight; $i++) {
				stdout::write("\033[".$lbound."G");																		// CHA		{$lbound}
				stdout::write(str_pad('', $gwidth * 2, static::CHAR_EMPTY));
				stdout::write("\033[B");																				// CUD		1
			}

			// Render field tetrominos.
			stdout::write("\033[1;1H");																					// CUP		1,1
			$lid = null;
			foreach ($this->grid as $row) {
				stdout::write("\033[".$lbound."G");																		// CHA		{$lbound}

				$ccon = 0;
				foreach ($row as $col) {
					$ccon++;
					if ($col === null) {
						if ($this->use_guides) {
							if ($ccon % 2 === 0) {
								stdout::write(static::CHAR_EMPTY_GUIDE);
							} else {
								stdout::write("\033[2C");
							}
						} else {
							stdout::write("\033[2C");                                                                        // CUF		2
						}
					} else {
						if ($this->use_guides || $lid !== $col) {
							$lid = $col;
							stdout::write("\033[48;5;" . $this->colors[$col] . "m");                               		// BG_256	{$this->colors[CELL_ID]}
						}

						stdout::write(static::CHAR_TETROMINO);
					}
				}

				stdout::write("\033[B");																				// CUD		1
			}

			// Cleanup.
			stdout::write("\033[0m");																					// SGR_0

			// Re-render tetromino.
			$this->renderFalling();
		}
	}

	protected function renderHUD($full=false) {
		$width  = $this->tsize[0];
		$height = $this->tsize[1];
		$gwidth = $this->gsize[0];
		$lbound = floor($width / 2) - floor($gwidth);
		$rbound = $width - $lbound;

		// Sidebars.
		if ($full) {
			$rwidth = $width - $rbound + 1;

			// Background.
			stdout::write("\033[1;1H");																					// CUP		1,1
			stdout::write("\033[38;5;" . Tuning::SIDEBAR_FG . "m");														// FG_256	{Tuning::SIDEBAR_FG}
			stdout::write("\033[48;5;" . Tuning::SIDEBAR_BG . "m");														// BG_256	{Tuning::SIDEBAR_BG}
			for ($i = 0; $i < $height; $i++) {
				stdout::write("\033[G");																				// CHA		1
				stdout::write(str_pad('', $lbound));																	// .FILL	0-{$lbound}
				stdout::write("\033[B");																				// CUD		1
			}

			stdout::write("\033[1;".$rbound."H");																		// CUP		1,{$rbound}
			stdout::write("\033[38;5;" . Tuning::SIDEBAR_FG . "m");														// FG_256	{Tuning::SIDEBAR_FG}
			stdout::write("\033[48;5;" . Tuning::SIDEBAR_BG . "m");														// BG_256	{Tuning::SIDEBAR_BG}
			for ($i = 0; $i < $height; $i++) {
				stdout::write(str_pad('', $rwidth));																	// .FILL	{$width-$rbound}
				stdout::write("\033[B");																				// CUD		1
				stdout::write("\033[".$rbound."G");																		// CHA		{$rbound}
			}

			// Text
			stdout::write("\033[1m");																					// TA_BOLD

			// Text: Hold
			$x = floor(($lbound / 2) - (static::UI_TBOX_WIDTH / 2));
			$y = 2;
			stdout::write("\033[".$y.";".$x."H");																		// CUP		{$y},{$x}
			stdout::write(str_pad("Hold:", $lbound - $x, ' ', STR_PAD_RIGHT));

			// Text: Score
			$y += static::UI_TBOX_HEIGHT + 2;
			stdout::write("\033[".$y.";3H");																			// CUP		{$y},3
			stdout::write("Score:");

			// Text: Level
			$y += 3;
			stdout::write("\033[".$y.";3H");																			// CUP		{$y},3
			stdout::write("Level:");

			// Text: Lines
			$y += 3;
			stdout::write("\033[".$y.";3H");																			// CUP		{$y},3
			stdout::write("Lines:");


			// Text: Next
			$x = $rbound + floor(($rwidth / 2) - (static::UI_TBOX_WIDTH / 2));
			stdout::write("\033[2;".$x."H");																			// CUP		2,{$x}
			stdout::write(str_pad("Next:", $rwidth, ' ', STR_PAD_RIGHT));

			// Cleanup.
			stdout::write("\033[0m");																					// SGR_0
		}

		// Held.
		if ($full || $this->render_held) {
			$this->render_held = false;
			$this->renderTetrominoBox(floor(($lbound / 2) - (static::UI_TBOX_WIDTH / 2)), 3, $this->tet_grid_held);
		}

		// Next.
		if ($full || $this->render_next) {
			$this->render_next = false;
			$rwidth = $width - $rbound + 1;
			$this->renderTetrominoBox($rbound + floor(($rwidth / 2) - (static::UI_TBOX_WIDTH / 2)), 3, $this->tet_grid_next);
		}

		// Score.
		if ($full || $this->render_score) {
			$this->render_score = false;

			$y = 2 + 1 + static::UI_TBOX_HEIGHT + 2;
			stdout::write("\033[38;5;" . Tuning::STAT_FG . "m");														// FG_256	{Tuning::STAT_FG}
			stdout::write("\033[48;5;" . Tuning::STAT_BG . "m");														// BG_256	{Tuning::STAT_BG}
			stdout::write("\033[".$y.";3H");																			// CUP		{$y},3
			stdout::write(str_pad($this->score, $lbound - 5, ' ', STR_PAD_RIGHT));
		}

		// Level.
		if ($full || $this->render_level) {
			$this->render_level = false;

			$y = 2 + 1 + static::UI_TBOX_HEIGHT + 2 + 3;
			stdout::write("\033[38;5;" . Tuning::STAT_FG . "m");														// FG_256	{Tuning::STAT_FG}
			stdout::write("\033[48;5;" . Tuning::STAT_BG . "m");														// BG_256	{Tuning::STAT_BG}
			stdout::write("\033[".$y.";3H");																			// CUP		{$y},3
			stdout::write(str_pad(($this->level+1), $lbound - 5, ' ', STR_PAD_RIGHT));
			//stdout::write(str_pad($this->tet_x.", ".$this->tet_y." & ".$this->update_fuse, $lbound - 5, ' ', STR_PAD_RIGHT));
		}

		// Lines.
		if ($full || $this->render_lines) {
			$this->render_lines = false;

			$y = 2 + 1 + static::UI_TBOX_HEIGHT + 2 + 3*2;
			stdout::write("\033[38;5;" . Tuning::STAT_FG . "m");														// FG_256	{Tuning::STAT_FG}
			stdout::write("\033[48;5;" . Tuning::STAT_BG . "m");														// BG_256	{Tuning::STAT_BG}
			stdout::write("\033[".$y.";3H");																			// CUP		{$y},3
			stdout::write(str_pad($this->lines, $lbound - 5, ' ', STR_PAD_RIGHT));
		}
	}

	protected function renderTetrominoBox($x, $y, $tet) {
		// Render black box.
		stdout::write("\033[".$y.";".$x."H");																			// CUP		{$x},{$y}
		stdout::write("\033[48;5;" . Tuning::GAME_BG . "m");															// BG_256	{Tuning::GAME_BG}
		for ($i = 0; $i < static::UI_TBOX_HEIGHT; $i++) {
			stdout::write("\033[".$x."G");																				// CHA		{$x}
			stdout::write(str_pad('', static::UI_TBOX_WIDTH, static::CHAR_EMPTY));										// .FILL    {static::UI_TBOX_WIDTH}
			stdout::write("\033[B");																					// CUD		1
		}

		// Calculate ideal coordinates for tetromino.
		$tw = count($tet[0]);
		$th = count($tet);

		$tx = $x + floor((static::UI_TBOX_WIDTH / 2) - ($tw));
		$ty = $y + ceil((static::UI_TBOX_HEIGHT / 2) - ($th / 2));

		// Render tetromino.
		if ($tet != null) {
			$this->renderTetromino($tx, $ty, $tet);
		}
	}

	protected function renderTetromino($x, $y, $tet) {
		// Render tetromino.
		stdout::write("\033[".$y.";".$x."H");																			// CUP		${y},${x}
		foreach ($tet as $row) {
			foreach ($row as $col) {
				if ($col === null) {
					stdout::write("\033[2C");																			// CUF		2
				} else {
					stdout::write("\033[48;5;" . $this->colors[$col] . "m");											// BG_256	{$this->colors[CELL_ID]}
					stdout::write(static::CHAR_TETROMINO);
				}

			}
			stdout::write("\033[".$x."G");																				// CHA		{x}
			stdout::write("\033[B");																					// CUD		1
		}
	}

	protected function renderClear() {
		$this->render_cleared = true;
		stdout::write("\033[0m\033[1;1H");
		for ($i = 0; $i < $this->tsize[1]; $i++) {
			stdout::write("\033[2K\033[B");
		}
	}

	protected function renderFlush() {
		stdout::flush();
	}

}

// Main
Terminal::prepare();
$main = new Termtris();
$main->init();
$main->reset();
$main->main();
Terminal::cleanup();


