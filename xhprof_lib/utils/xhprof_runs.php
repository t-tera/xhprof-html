<?php
//
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// This file defines the interface iXHProfRuns and also provides a default
// implementation of the interface (class XHProfRuns).
//

/**
 * iXHProfRuns interface for getting/saving a XHProf run.
 *
 * Clients can either use the default implementation,
 * namely XHProfRuns_Default, of this interface or define
 * their own implementation.
 *
 * @author Kannan
 */
interface iXHProfRuns {

  /**
   * Returns XHProf data given a run id ($run) of a given
   * type ($type).
   *
   * Also, a brief description of the run is returned via the
   * $run_desc out parameter.
   */
  public function get_run($run_id, $type, &$run_desc);

  /**
   * Save XHProf data for a profiler run of specified type
   * ($type).
   *
   * The caller may optionally pass in run_id (which they
   * promise to be unique). If a run_id is not passed in,
   * the implementation of this method must generated a
   * unique run id for this saved XHProf run.
   *
   * Returns the run id for the saved XHProf run.
   *
   */
  public function save_run($xhprof_data, $type, $run_id = null);
}


/**
 * XHProfRuns_Default is the default implementation of the
 * iXHProfRuns interface for saving/fetching XHProf runs.
 *
 * It stores/retrieves runs to/from a filesystem directory
 * specified by the "xhprof.output_dir" ini parameter.
 *
 * @author Kannan
 */
class XHProfRuns_Default implements iXHProfRuns {

  private $dir = XHPROF_DATA_DIR;
  private $suffix = 'xhprof';

  private function gen_run_id($type) {
    return uniqid();
  }

  private function file_name($run_id, $type) {

    $file = "$run_id.$type." . $this->suffix;
    if ($type === '' || $type === $this->suffix) {
      $file = "$run_id." . $this->suffix;
    }

    $file = str_replace(['\\','/',chr(0)], '', $file);

    if (!empty($this->dir)) {
      $file = $this->dir . "/" . $file;
    }
    return $file;
  }

  public function del_run($run_id, $type) {
    $file_name = $this->file_name($run_id, $type);
    $tmp = explode(".", $file_name);
    $ext = $tmp[count($tmp) - 1];
    if (count($tmp) >= 2 && $ext === $this->suffix) {
      return unlink($file_name);
    }
  }

  public function del_all_runs() {
    foreach (scandir($this->dir) as $fn) {
      $tmp = explode(".", $fn);
      $ext = $tmp[count($tmp) - 1];
      if (count($tmp) >= 2 && $ext === $this->suffix) {
        unlink("{$this->dir}/{$fn}");
      }
    }
  }

  public function get_run($run_id, $type, &$run_desc) {
    $file_name = $this->file_name($run_id, $type);

    if (!file_exists($file_name)) {
      xhprof_error("Could not find file $file_name");
      $run_desc = "Invalid Run Id = $run_id";
      return null;
    }

    $contents = file_get_contents($file_name);
    $run_desc = "XHProf Run (Namespace=$type)";
    return unserialize($contents);
  }

  public function save_run($xhprof_data, $type, $run_id = null) {

    // Use PHP serialize function to store the XHProf's
    // raw profiler data.
    $xhprof_data = serialize($xhprof_data);

    if ($run_id === null) {
      $run_id = $this->gen_run_id($type);
    }

    $file_name = $this->file_name($run_id, $type);
    $file = fopen($file_name, 'w');

    if ($file) {
      fwrite($file, $xhprof_data);
      fclose($file);
    } else {
      xhprof_error("Could not open $file_name\n");
    }

    // echo "Saved run in {$file_name}.\nRun id = {$run_id}.\n";
    return $run_id;
  }

  function list_runs($url_params) {
    if (is_dir($this->dir)) {
        echo "<hr/>Existing runs:";
        echo "<form method=post>";
        echo "<button name=\"delAllRuns\" value=\"1\" onclick=\"return confirm('Delete all?')\">DELETE ALL</button>";
        echo "\n<ul>\n";
        $files = glob("{$this->dir}/*.{$this->suffix}");
        usort($files, function($a,$b) {
            $tdiff = filemtime($b) - filemtime($a);
            return $tdiff === 0 ? strcmp($a, $b) : $tdiff;
        });
        foreach ($files as $file) {
            list($run,$source) = explode('.', basename($file));

            $base_url_params = xhprof_array_unset(xhprof_array_unset($url_params, 'run'), 'source');
            $base_url_params['run'] = $run;
            $base_url_params['source'] = $source;
            $base_url_params = '?' . http_build_query($base_url_params);

            $delUrl = $base_url_params. '&delRun=1';

            echo '<li>'
                . '<button formaction="'. htmlentities($delUrl). '">DEL</button>'
                . '&nbsp;'
                . '<a href="' . htmlentities($base_url_params) . '">'
                . htmlentities(basename($file)) . "</a><small> "
                . date("Y-m-d H:i:s", filemtime($file))
                . " - "
                . number_format(filesize($file))
                . " bytes</small></li>\n";
        }
        echo "</ul>\n";
        echo "</form>\n";
    }
  }

    function redir_to_list() {
        header('Location: .');
        exit(0);
    }
}
