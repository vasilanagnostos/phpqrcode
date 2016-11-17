<?php

namespace Ruslan03492\phpqrcode;
use Exception;
use Ruslan03492\phpqrcode\Inputs\QrInput;
use Ruslan03492\phpqrcode\Rs\QrRsBlock;
use Ruslan03492\phpqrcode\Rs\QrRs;

class QrRawCode {
  public $version;
  public $datacode = array();
  public $ecccode = array();
  public $blocks;
  public $rsblocks = array(); //of RSblock
  public $count;
  public $dataLength;
  public $eccLength;
  public $b1;

  //----------------------------------------------------------------------
  public function __construct(QrInput $input) {
    $spec = array(0, 0, 0, 0, 0);

    $this->datacode = $input->getByteStream();
    if (is_null($this->datacode)) {
      throw new Exception('null imput string');
    }

    QrSpec::getEccSpec($input->getVersion(), $input->getErrorCorrectionLevel(), $spec);

    $this->version = $input->getVersion();
    $this->b1 = QrSpec::rsBlockNum1($spec);
    $this->dataLength = QrSpec::rsDataLength($spec);
    $this->eccLength = QrSpec::rsEccLength($spec);
    $this->ecccode = array_fill(0, $this->eccLength, 0);
    $this->blocks = QrSpec::rsBlockNum($spec);

    $ret = $this->init($spec);
    if ($ret < 0) {
      throw new Exception('block alloc error');
      return NULL;
    }

    $this->count = 0;
  }

  //----------------------------------------------------------------------
  public function init(array $spec) {
    $dl = QrSpec::rsDataCodes1($spec);
    $el = QrSpec::rsEccCodes1($spec);
    $rs = QrRs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);


    $blockNo = 0;
    $dataPos = 0;
    $eccPos = 0;
    for ($i = 0; $i < QrSpec::rsBlockNum1($spec); $i++) {
      $ecc = array_slice($this->ecccode, $eccPos);
      $this->rsblocks[$blockNo] = new QrRsBlock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
      $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

      $dataPos += $dl;
      $eccPos += $el;
      $blockNo++;
    }

    if (QrSpec::rsBlockNum2($spec) == 0) {
      return 0;
    }

    $dl = QrSpec::rsDataCodes2($spec);
    $el = QrSpec::rsEccCodes2($spec);
    $rs = QrRs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);

    if ($rs == NULL) {
      return -1;
    }

    for ($i = 0; $i < QrSpec::rsBlockNum2($spec); $i++) {
      $ecc = array_slice($this->ecccode, $eccPos);
      $this->rsblocks[$blockNo] = new QRrsblock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
      $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

      $dataPos += $dl;
      $eccPos += $el;
      $blockNo++;
    }

    return 0;
  }

  //----------------------------------------------------------------------
  public function getCode() {
    $ret = NULL;

    if ($this->count < $this->dataLength) {
      $row = $this->count % $this->blocks;
      $col = $this->count / $this->blocks;
      if ($col >= $this->rsblocks[0]->dataLength) {
        $row += $this->b1;
      }
      $ret = $this->rsblocks[$row]->data[$col];
    }
    else {
      if ($this->count < $this->dataLength + $this->eccLength) {
        $row = ($this->count - $this->dataLength) % $this->blocks;
        $col = ($this->count - $this->dataLength) / $this->blocks;
        $ret = $this->rsblocks[$row]->ecc[$col];
      }
      else {
        return 0;
      }
    }
    $this->count++;

    return $ret;
  }
}
