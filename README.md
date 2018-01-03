<p>
  <h1 align="center">Maxon Reporter</h1>
  <p align="center">Machine Extensible Monitoring - Reporter</p>
</p>

## Description
*The Reporter* part of the Maxon bundle. Use this tool to send information about your machine's current operations.

## Requirements
- PHP 7+
- PHP `posix` extension enabled.

## Installation

***Convenient Oneliner™***:

`git clone https://github.com/mergado/maxon-reporter.git && cd maxon-reporter && chmod +x reporter && ./reporter`

*Note: `maxon-reporter` directory will be created in your current working directory.*

## Usage

- An `example` configuration is located at `./config/example.json`.
- Basic `gatherers` are located in `./gatherers` directory.
- Use the `./reporter` to manage the whole thing.

### Examples:
- `./reporter --help`
  - Display available options.
- `./reporter --config config/example.json`
  - Fire a single gathering according to the configuration specified in the `config/example.json` file. Gathered results will be printed out.
- `./reporter --config config/example.json --interval 30`
  - Gather data according to the configuration specified in the `config/example.json` file, do it every thirty *(`--interval 30`)* seconds and send gathered data to the endpoint specified in the config file's `"target"` field. Gathered results will be printed out.
- `./reporter --config config/example.json --interval 30 --daemonize`
  - The same as the one above, but daemonize the reporter and send it to background.
- `./reporter --pid`
  - Return the current running daemonized reporter's PID, if it exists.

## Configuration

An `example.json` configuration file is provided. Following fields are mandatory, unless specified otherwise:
- `target` field: URL where to send the resulting JSON.
- `gatherers` field: An array of gatherers to use. Gatherers must be placed in the `./gatherers` directory and must have the `.mex` extension *(eg. `./gatherers/machine.mex`)*
- `env` *(optional)* field: Environment variables which will then be available during a gatherer's execution.
- `payload` field: The template payload from which the resulting JSON will be constructed.

## Gatherers

### What is a gatherer?
*A gatherer* is a script which reports back some information about the current state of the machine *(or whatever needed)*. The script's [shebang](https://en.wikipedia.org/wiki/Shebang_(Unix)) is honored, and thus any language which supports it can be used to write a gatherer.

### How does a gatherer report information?
*A gatherer* is executed by *the reporter*. *The gatherer's* standard output is then parsed by *the reporter* as an `.ini` string and values (variables) defined this way are then available to use in the `payload` template *(which, again, is defined in the config file)*.

### Example

#### Gatherer's output
```
storage.number_of_directories=456
storage.number_of_files=1534
books.last_word_in_the_newest_book=Serendipity
```

#### Using variables in the payload template
```
...
	"id.storage_dirs.or_whatever": { // This is an arbitrary ID and does not have to match the variable name.
		"title": "Directories",
		"type": "number",
		"value": "${storage.number_of_directories}", // The same ID (variable name) as reported by the gatherer.
		"config": {
			"unit": "dirs"
		}
	},
	"id.storage_files": {
		"title": "Files",
		"type": "number",
		"value": "${storage.number_of_files / 1000}", // Expressions can be used for advanced computations.
		"config": {
			"unit": "kilofiles"
		}
	},
	"id.word": {
		"title": "Last word",
		"type": "string",
		"value": "${books.last_word_in_the_newest_book}",
	},
...
```
