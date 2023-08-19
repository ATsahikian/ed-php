#!/usr/bin/php
<?php
class Buffer
{
    public $file_name = 'default.txt';
    public $lines = array();
    public $last_index = -1;
}

class Command
{
    public $first = null;
    public $delimitor = null;
    public $last = null;
    public $command = null;
    public $parameters = null;
}

function line_index_first(Command $c, Buffer $b)
{
    if ($c->delimitor && !$c->first)
        return 0;
    if ($c->first == '.' || !$c->first)
        return $b->last_index;
    return $c->first-1;
}

function line_index_last(Command $c, Buffer $b)
{
    if (!$c->delimitor)
        return line_index_first($c, $b);

    if ($c->last == '$' || !$c->last)
        return count($b->lines)-1;
    if ($c->last == '.')
        return $b->last_index;
    return $c->last-1;
}

function enter_insert_mode(int $first_index, int $lines_to_delete)
{
    global $stdin;
    global $buffer;

    $lines = array();
    while (true)
    {
        $line = fgets($stdin);
        $line = rtrim($line, "\r\n");
        if ($line == ".")
            break;
        $lines[] = $line;
    }
    array_splice($buffer->lines, $first_index, $lines_to_delete, $lines);
    $buffer->last_index = $first_index + count($lines)-1;
}

function command_insert(Command $c)
{
    global $buffer;
    $first = (int)line_index_first($c, $buffer);
    enter_insert_mode($first, 0);
}

function command_append(Command $c)
{
    global $buffer;
    $last = (int)line_index_last($c, $buffer);
    enter_insert_mode($last+1, 0);
}

function command_change(Command $c)
{
    global $buffer;
    $first = (int)line_index_first($c, $buffer);
    $last = (int)line_index_last($c, $buffer);

    enter_insert_mode($first, $last-$first+1);
}

function command_number(Command $c)
{
    global $buffer;
    print("{$buffer->lines[$c->command-1]}\n");
}

function command_print(Command $c)
{
    global $buffer;

    $first = line_index_first($c, $buffer);
    $last = line_index_last($c, $buffer);
    for ($i = $first; $i <= $last; $i++)
    {
        print("{$buffer->lines[$i]}\n");
    }
}

function command_write(Command $c)
{
    global $buffer;

    $file_name = $c->parameters ? $c->parameters : $buffer->file_name;
    
    $f = fopen($file_name, "w");
    foreach($buffer->lines as $line)
    {
        fwrite($f, $line."\n");
    }    
}

function command_edit(Command $c)
{
    global $buffer;

    $file_name = $c->parameters ? $c->parameters : $buffer->file_name;
    
    $f = fopen($file_name, "r");

    $lines = array();
    while (true)
    {
        $line = fgets($f);
        if ($line === false)
            break;
        $line = rtrim($line, "\r\n");
        $lines[] = $line;
    }

    $buffer->lines = $lines;
    $buffer->last_index=count($lines)-1;
}

function command_delete(Command $c)
{
    global $buffer;

    $first = line_index_first($c, $buffer);
    $last = line_index_last($c, $buffer);

    array_splice($buffer->lines, $first, $last-$first+1);
    if (count($buffer->lines) == $first)
        $buffer->last_index = $first-1;
    else
        $buffer->last_index = $first;
}

function main()
{
    global $argv;
    global $stdin;

    while (true)
    {
        print(": ");
        $command_line = trim(fgets($stdin));

        // [address[,address]]command[parameters]
        $result = preg_match(
            '/^(\d*?|\.)(?:(,)(\d*?|\$))?(\d+|[a-z])(?:\s+([0-9a-zA-Z_.]+))?$/', 
            $command_line, 
            $match);
        if ($result && count($match) >= 5)
        {
            $command = new Command;
            $command->first = $match[1];
            $command->delimitor = $match[2];
            $command->last = $match[3];
            $command->command = $match[4];
            if (count($match)==6)
                $command->parameters = $match[5];

            switch ($command->command)
            {
                case 'a':
                    command_append($command);
                    break;
                case 'i':
                    command_insert($command);
                    break;
                case 'p':
                    command_print($command);
                    break;
                case 'e':
                    command_edit($command);
                    break;
                case 'd':
                    command_delete($command);
                    break;
                case 'c':
                    command_change($command);
                    break;
                case 'w':
                    command_write($command);
                    break;
                case 'q':
                    return;
                case 'r':
                    print("Error: command is not implemented\n");    
                    break;
                default:
                    command_number($command);
            }
        }
        else
        {
            print("Error: invalid command.\n");
        }    
    }
}

$stdin = fopen('php://stdin', 'r');
$buffer = new Buffer;

main();
?>
