<?php

namespace Mdanter\Ecc\Serializer\Point;

use Mdanter\Ecc\PointInterface;
use Mdanter\Ecc\CurveFpInterface;
use Mdanter\Ecc\Serializer\Util\CurveOidMapper;
use Mdanter\Ecc\MathAdapterInterface;
use Mdanter\Ecc\NumberTheory;

class CompressedPointSerializer implements PointSerializerInterface
{

    private $adapter;

    private $debug = false;

    public function __construct(MathAdapterInterface $adapter, $debug = false)
    {
        $this->adapter = $adapter;
        $this->debug = (bool)$debug;
    }

    public function serialize(PointInterface $point)
    {
        $length = CurveOidMapper::getByteSize($point->getCurve()) * 2;

        $hexString = $this->adapter->mod($point->getY(), 2) == 0 ? '02' : '03';
        $hexString .= str_pad($this->adapter->decHex($point->getX()), $length, '0', STR_PAD_LEFT);

        return $hexString;
    }

    public function unserialize(CurveFpInterface $curve, $data)
    {
        if (substr($data, 0, 2) != '03' && substr($data, 0, 2) != '02') {
            throw new \InvalidArgumentException('Invalid data: incorrect leading bit.');
        }

        $theory = new NumberTheory($this->adapter);

        $prefix = substr($data, 0, 2);
        $data = substr($data, 2);

        $x = $this->adapter->hexDec($data);
        $x3 = $this->adapter->powmod($x, '3', $curve->getPrime());

        $y2 = $this->adapter->add($x3, $curve->getB());
        $y0 = $theory->sqrtModP($y2, $curve->getPrime());
        $y1 = $this->adapter->sub($curve->getPrime(), $y0);

        if ($prefix == '02') {
            $y = ($this->adapter->mod($y0, 2) == '0') ? $y0 : $y1;
        }
        else {
            $y = ($this->adapter->mod($y0, 2) == '0') ? $y1 : $y0;
        }

        return $curve->getPoint($x, $y);
    }
}
