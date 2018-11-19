# Termtris

A terminal-based version of Tetris written in PHP.

![Screenshot](README.assets/image-20181119125801103-2661081.png)

## Features

- Async input. You don't need to press a button for things to happen!
- Pseudorandom tetromino shuffling. Just like the classic!
- Hold a tetromino for later.
- Instant dropping.
- Fast dropping.
- Difficulty scaling.
- Guide lines.



## Requirements

- PHP 7
- `stty`



## Controls

| Key       | Action                                              |
| --------- | --------------------------------------------------- |
| `<up>`    | Instantly drop the tetromino.                       |
| `<down>`  | Hold to increase the drop speed (and score!).       |
| `<left>`  | Move the tetromino to the left.                     |
| `<right>` | Move the tetromino to the right.                    |
| `<space>` | Swap the falling tetromino with the held tetromino. |
| `,`       | Rotate counterclockwise.                            |
| `.`       | Rotate clockwise.                                   |
| `g`       | Enable guide lines.                                 |
| `p`       | Toggle pause.                                       |
| `+`       | [Debug] Increase the difficulty level.              |
| `-`       | [Debug] Decrease the difficulty level.              |

