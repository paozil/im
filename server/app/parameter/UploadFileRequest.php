<?php
/**
 * Created by PhpStorm.
 * User: locust
 * Date: 2019/12/11
 * Time: 17:33
 */

namespace api;


class UploadFileRequest extends Request {
  /**
   * @var file
   * @uses required
   */
  public $file;
}