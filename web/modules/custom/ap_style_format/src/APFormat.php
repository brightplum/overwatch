<?php

namespace Drupal\ap_style_format;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class DefaultService.
 *
 * @package Drupal\ap_style_format
 */
class APFormat extends AbstractExtension {

  /**
   * Convert twig variable into AP Format.
   */
  public function getFilters() {
    return [
      new TwigFilter('apDate', [$this, 'apDateFormat']),
    ];
  }

  /**
   * Gets a unique identifier for this Twig extension.
   */
  public function getName() {
    return 'ap_style_format.twig_extension';
  }

  /**
   * Replace date format with AP format.
   *
   * @param int $date
   *   Unix date that is passed for formatting.
   * @param string $dateFormat
   *   The php date format to be converted to AP Style.
   * @param string $timezone
   *   The php timezone for the conversion.
   *
   * @return string
   *   Returns the date as a string in AP Style format.
   */
  public static function apDateFormat($date, $dateFormat = "mdy", $timezone = 'America/New_York') {
    // Drupal returns on empty array when no value is present. For now skip.
    if (!is_array($date)) {
      if (!APFormat::isValidTimeStamp($date)) {
        // Try and create date based on the given format.
        $date = is_null($date) ? '' : strtotime($date);

        if (!$date) {
          // If the date is not a valid timestamp.
          if (!empty($date)) {
            $date = new DrupalDateTime($date, 'UTC');
          }
          else {
            $date = new DrupalDateTime('UTC');
          }

          $date->setTimezone(new \DateTimeZone($timezone));
          $date = $date->getTimestamp();
        }
      }
      date_default_timezone_set($timezone);
      if (strtolower($dateFormat) == "m") {
        $apDate = APFormat::mapMonth(date("M", $date));
      }
      elseif ($dateFormat == "d") {
        $apDate = date("j", $date);
      }
      elseif (strtolower($dateFormat) == "y") {
        $apDate = date("Y", $date);
      }
      elseif (strtolower($dateFormat) == "mdy") {
        $apDate = APFormat::mapMonth(date("M", $date)) . " " . date("j", $date) . ", " . date("Y", $date);
      }
      elseif (strtolower($dateFormat) == "m/d/y") {
        $apDate = date("n", $date) . "/" . date("j", $date) . "/" . date("Y", $date);
      }
      elseif (strtolower($dateFormat) == "h") {
        $apDate = str_replace(['am', 'pm'],
          ['a.m.', 'p.m.'],
          date("g:i a", $date)
        );
      }
      else {
        $apDate = date($dateFormat, $date);
      }
      return $apDate;
    }
  }

  /**
   * Format time for AP style.
   *
   * @param int $time
   *   The datetime as a timestamp.
   * @param bool $capnoon
   *   Should we capatalize the wood Noon?
   *
   * @return string
   *   The apformat of the hour
   */
  public static function apTime($time, $capnoon = TRUE) {
    // Format am and pm to AP Style abbreviations.
    if (date('a', $time) == 'am') {
      $meridian = 'a.m.';
    }
    elseif (date('a', $time) == 'pm') {
      $meridian = 'p.m.';
    }

    // Reformat 12:00 and 00:00 to noon and midnight.
    if (date('H:i', $time) == '00:00') {
      if (TRUE == $capnoon) {
        $aptime = 'Midnight';
      }
      else {
        $aptime = 'midnight';
      }
    }
    elseif (date('H:i', $time) == '12:00') {
      if (TRUE == $capnoon) {
        $aptime = 'Noon';
      }
      else {
        $aptime = 'noon';
      }
    }
    elseif (date('i', $time) == '00') {
      $aptime = date('g', $time) . ' ' . $meridian;
    }
    else {
      $aptime = date('g:i', $time) . ' ' . $meridian;
    }

    return $aptime;
  }

  /**
   * Remaps the months to AP format.
   */
  public static function mapMonth($month) {
    $months = (object) [
      'Jan' => 'Jan.',
      'Feb' => 'Feb.',
      'Mar' => 'March',
      'Apr' => 'April',
      'May' => 'May',
      'Jun' => 'June',
      'Jul' => 'July',
      'Aug' => 'Aug.',
      'Sep' => 'Sept.',
      'Oct' => 'Oct.',
      'Nov' => 'Nov.',
      'Dec' => 'Dec.',
    ];
    return ($months->$month);
  }

  /**
   * Checks if timestamp is valid unix.
   */
  public static function isValidTimeStamp($timestamp) {
    if (empty($timestamp) || is_array($timestamp)) {
      return FALSE;
    }
    try {
      return ((string) (int) (string) $timestamp === $timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
