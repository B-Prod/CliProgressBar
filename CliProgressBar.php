<?php

/**
 * @file
 * Provide a PHP CLI progree bar.
 */

/**
 * The CLI Progress Bar class.
 */
class CliProgressBar {

  /**
   * The character that represents the done work.
   */
  const DONE_CHARACTER = '=';

  /**
   * The character that represents the remaining work.
   */
  const EMPTY_CHARACTER = ' ';

  /**
   * The default CLI window width.
   */
  const DEFAULT_WINDOW_WIDTH = 80;

  /**
   * Flag indicating whether all the processes are finished.
   *
   * @var boolean
   */
  protected $finished = FALSE;

  /**
   * The time the progress bar was initialized.
   *
   * @var int
   */
  protected static $start_time;

  /**
   * The total number of operations.
   *
   * @var int
   */
  protected $total;

  /**
   * The progress bar size.
   *
   * @var int
   */
  protected $size;

  /**
   * Flag indicating if the percentage information is displayed.
   *
   * @var boolean
   */
  protected $percentage;

  /**
   * Flag indicating if the operation count is displayed.
   *
   * @var boolean
   */
  protected $counter;

  /**
   * Flag indicating if the remaining time is displayed.
   *
   * @var boolean
   */
  protected $timer;

  /**
   * The width od the command line window.
   *
   * @var int
   */
  protected $windowWidth;

  /**
   * Class constructor.
   *
   * @param $total
   *   The total number of operations.
   * @param $size
   *   The progress bar size. the minimum size is 10.
   * @param $precentage
   *   Whether the percentage should be displayed.
   * @param $counter
   *   Whether the operation count should be displayed.
   * @param $timer
   *   Whether the remaining time should be displayed.
   *
   * @return CliProgressBar
   */
  public function __construct($total, $size = 40, $percentage = TRUE, $counter = TRUE, $timer = TRUE) {
    if ($total < 1) {
      throw new CliProgressBarException('There should be at least one operation.');
    }

    if ($size < 10) {
      throw new CliProgressBarException('The minimum progress bar size is 10.');
    }

    $this->total = (int) $total;
    $this->size = (int) $size;
    $this->percentage = (bool) $percentage;
    $this->counter = (bool) $counter;
    $this->timer = (bool) $timer;

    $this->adjustProgressBarSize();

    self::$start_time = time();

    // Capture all output.
    ob_start();
  }

  /**
   * Adjust the progress bar size if necessary.
   *
   * This method first tries to remove some options then if the window
   * width is still too small, the progress bar length is decreased.
   */
  protected function adjustProgressBarSize() {
    // Try to detect the window width.
    $this->windowWidth = preg_match('/windows/i', php_uname('s')) ? $this->getTerminalSizeOnWindows() : $this->getTerminalSizeOnLinux();

    if (empty($this->windowWidth)) {
      $this->windowWidth = self::DEFAULT_WINDOW_WIDTH;
      print sprintf('Impossible to detect the window size. Default value of %d is used.%s', self::DEFAULT_WINDOW_WIDTH, PHP_EOL);
    }

    if ($this->windowWidth < 10) {
      throw new CliProgressBarException('The command line window is not large enough.');
    }

    $width = $this->size + array_sum($sizes = $this->optionsSizes());

    // If the required width is greather than the available one...
    while ($width > $this->windowWidth && list($option, $size) = each($sizes)) {
      $width -= $size;
      $this->{$option} = FALSE;
    }

    // Is the width still too large?
    if ($width > $this->windowWidth) {
      $this->size = $this->windowWidth;
    }
  }

  /**
   * Get the options sizes.
   */
  protected function optionsSizes() {
    $sizes = array();

    if ($this->percentage) {
      $sizes['percentage'] = 5;
    }

    if ($this->counter) {
      $sizes['counter'] = 2 * (strlen($this->total) + 1);
    }

    if ($this->timer) {
      $sizes['timer'] = 21;
    }

    krsort($sizes);
    return $sizes;
  }

  /**
   * Display the progress bar.
   *
   * @param $current
   *   The current operation number.
   */
  public function display($current) {
    if ($this->finished) {
      return;
    }

    $current = $current > $this->total ? $this->total : (int) $current;
    $ratio = (double) ($current / $this->total);
    $progress = ceil($ratio * ($this->size - 2));

    $output = '[' . str_repeat(self::DONE_CHARACTER, $progress) . str_repeat(self::EMPTY_CHARACTER, ($this->size - $progress - 2)) . ']';

    if ($current === $this->total) {
      $this->finished = TRUE;
    }

    if ($this->percentage) {
      $output .= sprintf(' %3d%%', round($ratio * 100, 1));
    }

    if ($this->counter) {
      $output .= sprintf(' %' . strlen($this->total) . 'd/%d', $current, $this->total);
    }

    if ($this->timer) {
      if ($this->finished) {
        $interval = '0s';
      }
      elseif ($remaining = ceil((time() - self::$start_time) * ($this->total - $current) / $current)) {
        $datetime = new DateTime();
        $datetime2 = clone($datetime);
        $datetime2->add(new DateInterval('PT' . $remaining . 'S'));
        $interval = ltrim($datetime->diff($datetime2)->format('%Y-%m-%d %Hh%im%ss'), '0- hm');
      }
      else {
        $interval = '---';
      }

      $output .= sprintf(' %20s', $interval);
    }

    // Clear all buffers.
    while (@ob_end_clean());

    print $output . ($this->finished ? PHP_EOL : "\r");
    flush();

    // Recreate a buffer if necessary.
    if (!$this->finished) {
      ob_start();
    }
  }

  /**
   * Get the window size on Linux.
   *
   * @see http://stackoverflow.com/questions/2203437/how-to-get-linux-console-columns-and-rows-from-php-cli#answer-2204201
   */
  protected function getTerminalSizeOnLinux() {
    return exec('tput cols');
  }

  /**
   * Get the window size on Windows.
   *
   * @see http://stackoverflow.com/questions/263890/how-do-i-find-the-width-height-of-a-terminal-window#answer-7575044
   */
  protected function getTerminalSizeOnWindows() {
    $output = array();
    $width = 0;
    exec('mode', $output);

    foreach($output as $line) {
      $match = array();

      if (preg_match('/^\s*columns\:?\s*(\d+)\s*$/i', $line, $matches)) {
        $width = intval($matches[1]);
        break;
      }
    }

    return $width;
  }

}

/**
 * The Exception class related to CLI Progress Bar.
 */
class CliProgressBarException extends Exception {}
