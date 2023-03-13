<?php

namespace Opmvpc\Sim8086;

class Sim
{
    private static array $registers = [
        'al',
        'cl',
        'dl',
        'bl',
        'ah',
        'ch',
        'dh',
        'bh',
        'ax',
        'cx',
        'dx',
        'bx',
        'sp',
        'bp',
        'si',
        'di',
    ];

    private static array $addressingModes = [
        'bx + si',
        'bx + di',
        'bp + si',
        'bp + di',
        'si',
        'di',
        'bp',
        'bx',
    ];

    public static function run(string $filename): void
    {
        $bytes = static::readBytes($filename);
        $result = 'bits 16'.PHP_EOL.PHP_EOL;
        $index = 1;
        while ($index <= count($bytes)) {
            $byte = $bytes[$index];

            // opcodes
            if (0b100010 === $byte >> 2) {
                $instruction = static::movRegMemToFromReg($bytes, $index);
                $result .= $instruction.PHP_EOL;
            } elseif (0b1011 === $byte >> 4) {
                $instruction = static::movImmediateToReg($bytes, $index);
                $result .= $instruction.PHP_EOL;
            } elseif (0b1100011 === $byte >> 1) {
                $instruction = static::movImmediateToRegMem($bytes, $index);
                $result .= $instruction.PHP_EOL;
            } elseif (0b1010000 === $byte >> 1) {
                $instruction = static::movMemToAcc($bytes, $index);
                $result .= $instruction.PHP_EOL;
            } elseif (0b1010001 === $byte >> 1) {
                $instruction = static::movAccToMem($bytes, $index);
                $result .= $instruction.PHP_EOL;
            } else {
                exit('opcode not found: '.decbin($byte).PHP_EOL);
            }

            // echo $instruction.PHP_EOL;
        }

        static::render($result);
    }

    public static function readBytes(string $filename): array
    {
        $bytes = file_get_contents($filename);

        return unpack('C*', $bytes);
    }

    public static function movRegMemToFromReg(array $bytes, int &$index): string
    {
        $d = ($bytes[$index] & 0b00000010) >> 1;
        $w = $bytes[$index] & 0b00000001;

        $mod = ($bytes[$index + 1] & 0b11000000) >> 6;
        $reg = ($bytes[$index + 1] & 0b00111000) >> 3;
        $rm = $bytes[$index + 1] & 0b00000111;

        // Register mode, no displacement
        if (0b11 === $mod) {
            $src = static::$registers[($w << 3) | $rm];
            $dst = static::$registers[($w << 3) | $reg];

            $register = [
                'src' => $src,
                'dst' => $dst,
            ];

            $instruction = 'mov ';

            if (0 === $d) {
                $instruction .= $register['src'].', '.$register['dst'];
            } else {
                $instruction .= $register['dst'].', '.$register['src'];
            }

            $index += 2;

            return $instruction;
        }

        // Memory mode
        $dst = static::$registers[($w << 3) | $reg];

        $addressingMode = static::$addressingModes[$rm];

        $displacement = null;
        if (0b00 === $mod) {
            $index += 2;
        } elseif (0b01 === $mod) {
            $displacement = unpack('c', pack('c', $bytes[$index + 2]))[1];
            $index += 3;
        } elseif (0b10 === $mod) {
            $displacement = unpack('s', pack('C*', $bytes[$index + 2], $bytes[$index + 3]))[1];
            $index += 4;
        }

        // direct addressing mode
        if (0b110 === $rm && 0b00 === $mod) {
            $addressingMode = unpack('s', pack('C*', $bytes[$index], $bytes[$index + 1]))[1];
            $displacement = null;
            $index += 2;
        }

        $src = '['.$addressingMode;
        if ($displacement) {
            if ($displacement < 0) {
                $src .= ' - '.abs($displacement);
            } else {
                $src .= ' + '.$displacement;
            }
        }
        $src .= ']';

        if (0 === $d) {
            return 'mov '.$src.', '.$dst;
        }

        return 'mov '.$dst.', '.$src;
    }

    public static function render($result): void
    {
        echo $result;
    }

    private static function movImmediateToReg($bytes, &$index): string
    {
        $w = ($bytes[$index] & 0b00001000) >> 3;
        $reg = ($bytes[$index] & 0b00000111);

        $register = static::$registers[($w << 3) | $reg];

        if (0 === $w) {
            // We pack and unpack to get the signed value
            $value = unpack('c', pack('c', $bytes[$index + 1]))[1];
            $index += 2;
        } else {
            $value = unpack('s', pack('C*', $bytes[$index + 1], $bytes[$index + 2]))[1];
            $index += 3;
        }

        return sprintf('mov %s, %d', $register, $value);
    }

    private static function printBytes($bytes): void
    {
        $index = 1;
        foreach ($bytes as $byte) {
            echo $index.' '.decbin($byte).PHP_EOL;
            ++$index;
        }
    }

    private static function movImmediateToRegMem(array $bytes, int &$index): string
    {
        $w = ($bytes[$index] & 0b00000001);
        $mod = ($bytes[$index + 1] & 0b11000000) >> 6;
        $rm = $bytes[$index + 1] & 0b00000111;

        $addressingMode = static::$addressingModes[$rm];

        $displacement = null;
        if (0b00 === $mod) {
            $index += 2;
        } elseif (0b01 === $mod) {
            $displacement = unpack('c', pack('c', $bytes[$index + 2]))[1];
            $index += 3;
        } elseif (0b10 === $mod) {
            $displacement = unpack('s', pack('C*', $bytes[$index + 2], $bytes[$index + 3]))[1];
            $index += 4;
        }

        $dest = '['.$addressingMode;
        if ($displacement) {
            if ($displacement < 0) {
                $dest .= ' - '.abs($displacement);
            } else {
                $dest .= ' + '.$displacement;
            }
        }
        $dest .= ']';

        $value = null;
        if (0 === $w) {
            $value = unpack('c', pack('c', $bytes[$index]))[1];
            ++$index;

            return sprintf('mov %s, byte %d', $dest, $value);
        }
        $value = unpack('s', pack('C*', $bytes[$index], $bytes[$index + 1]))[1];
        $index += 2;

        return sprintf('mov %s, word %d', $dest, $value);
    }

    private static function movMemToAcc(array $bytes, int &$index): string
    {
        $addr = unpack('s', pack('C*', $bytes[$index + 1], $bytes[$index + 2]))[1];
        $index += 3;

        return sprintf('mov ax, [%d]', $addr);
    }

    private static function movAccToMem(array $bytes, int &$index): string
    {
        $addr = unpack('s', pack('C*', $bytes[$index + 1], $bytes[$index + 2]))[1];
        $index += 3;

        return sprintf('mov [%d], ax', $addr);
    }
}
